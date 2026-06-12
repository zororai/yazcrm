<?php

namespace App\Jobs;

use Anthropic\Laravel\Facades\Anthropic;
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

class TranscribeAndDraftNotes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public readonly int $recordingId) {}

    public function handle(YeastarService $yeastar): void
    {
        $recording = Recording::find($this->recordingId);
        if (!$recording) return;

        $recording->update(['transcription_status' => 'processing']);

        try {
            // 1. Get a download URL from Yeastar
            $url = $recording->file_url
                ?? $yeastar->getRecordingDownloadUrl($recording->file_name);

            if (!$url) {
                $recording->update(['transcription_status' => 'failed']);
                Log::warning("TranscribeAndDraftNotes: no URL for recording {$this->recordingId}");
                return;
            }

            // 2. Download the audio file into a temp file
            $tmpPath = tempnam(sys_get_temp_dir(), 'rec_') . '.wav';
            $response = Http::withoutVerifying()->timeout(120)->get($url);

            if (!$response->successful()) {
                $recording->update(['transcription_status' => 'failed']);
                Log::warning("TranscribeAndDraftNotes: download failed for recording {$this->recordingId}");
                return;
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

            // 4. Generate counsellor notes with Claude
            $message = Anthropic::messages()->create([
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 500,
                'system'     => 'You are assisting a counsellor at a helpline. Based on the call transcript below, write a concise professional counsellor session note in 4-6 sentences. Cover: presenting issue, emotional state of the caller, interventions discussed, and any referrals or follow-up actions. Use neutral, non-judgmental clinical language. Do not invent details not present in the transcript.',
                'messages'   => [
                    ['role' => 'user', 'content' => "Call transcript:\n\n{$transcript}\n\nDraft a counsellor session note."],
                ],
            ]);

            $aiNotes = $message->content[0]->text ?? '';

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
