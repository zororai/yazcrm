<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Services\YeastarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class TranscribeAndDraftNotes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 300;

    public function __construct(public readonly int $recordingId) {}

    public function backoff(): array
    {
        // The recording often isn't finalized on the PBX the instant the
        // call-end webhook fires — give it time to actually become
        // available instead of failing permanently on the first attempt.
        return [15, 30, 60];
    }

    public function handle(YeastarService $yeastar): void
    {
        $recording = Recording::find($this->recordingId);
        if (!$recording) return;

        $recording->update(['transcription_status' => 'processing']);

        try {
            // 1. Get a download URL from Yeastar. The recording is often not
            // finalized on the PBX the instant the call-end webhook fires,
            // so poll briefly in-process before giving up — this matters
            // regardless of queue driver, and is the only thing that helps
            // under QUEUE_CONNECTION=sync, where $tries/backoff() never
            // actually get a chance to run (sync executes a job exactly
            // once per dispatch — there's no worker loop to retry it).
            $url = $recording->file_url;
            for ($attempt = 0; ! $url && $attempt < 3; $attempt++) {
                if ($attempt > 0) {
                    sleep(3);
                }
                $url = $yeastar->getRecordingDownloadUrl($recording->file_name);
            }

            if (!$url) {
                Log::warning("TranscribeAndDraftNotes: no URL yet for recording {$this->recordingId} (attempt {$this->attempts()})");
                throw new RuntimeException('Recording not yet available from PBX.');
            }

            // 2. Download the audio file into a temp file
            $tmpPath = tempnam(sys_get_temp_dir(), 'rec_') . '.wav';
            $response = Http::withoutVerifying()->timeout(120)->get($url);

            if (!$response->successful()) {
                Log::warning("TranscribeAndDraftNotes: download failed for recording {$this->recordingId} (attempt {$this->attempts()}, HTTP {$response->status()})");
                throw new RuntimeException("Recording download failed (HTTP {$response->status()}).");
            }

            file_put_contents($tmpPath, $response->body());

            // 3. Transcribe with Whisper
            $transcribeResponse = OpenAI::audio()->transcribe([
                'model' => 'whisper-1',
                'file'  => fopen($tmpPath, 'rb'),
                'response_format' => 'text',
            ]);

            @unlink($tmpPath);

            $transcript = is_string($transcribeResponse)
                ? $transcribeResponse
                : ($transcribeResponse->text ?? '');

            if (empty(trim($transcript))) {
                $recording->update(['transcription_status' => 'failed']);
                return;
            }

            // 4. Generate counsellor notes with OpenAI (same provider as the
            // Whisper transcription step above, and the same one used by
            // CaseIntelligenceService for the multilingual pipeline).
            $message = OpenAI::chat()->create([
                'model'      => 'gpt-4o-mini',
                'max_tokens' => 500,
                'messages'   => [
                    ['role' => 'system', 'content' => 'You are assisting a counsellor at a helpline. Based on the call transcript below, write a concise professional counsellor session note in 4-6 sentences. Cover: presenting issue, emotional state of the caller, interventions discussed, and any referrals or follow-up actions. Use neutral, non-judgmental clinical language. Do not invent details not present in the transcript.'],
                    ['role' => 'user', 'content' => "Call transcript:\n\n{$transcript}\n\nDraft a counsellor session note."],
                ],
            ]);

            $aiNotes = $message->choices[0]->message->content ?? '';

            $recording->update([
                'transcript'           => $transcript,
                'ai_notes'             => $aiNotes,
                'transcription_status' => 'done',
            ]);

        } catch (\Throwable $e) {
            $recording->update(['transcription_status' => 'failed']);
            Log::error("TranscribeAndDraftNotes failed for recording {$this->recordingId}: " . $e->getMessage());
            throw $e;
        }
    }
}
