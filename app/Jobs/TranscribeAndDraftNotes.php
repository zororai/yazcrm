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
use Illuminate\Support\Facades\Process;
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

            // 2. Download the audio file into a temp file. tempnam() creates
            // a file with no extension; appending '.wav' to the returned
            // path as a string (the old code) writes to a *different* path
            // than the one tempnam actually created, orphaning it. Build
            // the path correctly instead.
            // Yeastar's download endpoint requires the same Authorization
            // token as every other API call — it is NOT a self-contained
            // pre-signed URL. Downloading without it silently returns a
            // small JSON {"errcode":10004,"errmsg":"TOKEN EXPIRED"} body
            // instead of audio, which OpenAI then rejects as "Invalid file
            // format" — the real bug had nothing to do with file format.
            $downloadPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rec_' . uniqid() . '.download';
            $response = Http::withoutVerifying()->withHeaders(['Authorization' => $yeastar->getAccessToken()])->timeout(120)->get($url);

            if (!$response->successful()) {
                Log::warning("TranscribeAndDraftNotes: download failed for recording {$this->recordingId} (attempt {$this->attempts()}, HTTP {$response->status()})");
                throw new RuntimeException("Recording download failed (HTTP {$response->status()}).");
            }

            // Yeastar returns HTTP 200 even for its own API errors (e.g. an
            // expired token), with a small JSON body instead of audio —
            // catch that explicitly rather than letting it fail opaquely
            // inside ffmpeg/Whisper.
            $body = $response->body();
            if (str_starts_with(ltrim($body), '{')) {
                Log::warning("TranscribeAndDraftNotes: download returned an API error body for recording {$this->recordingId}: " . substr($body, 0, 200));
                throw new RuntimeException('Recording download returned an error instead of audio (possibly an expired PBX token).');
            }

            file_put_contents($downloadPath, $body);

            // 2b. Normalize with ffmpeg before handing to Whisper — Yeastar
            // recordings aren't guaranteed to be a codec/container Whisper's
            // API accepts as-is.
            $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rec_' . uniqid() . '.wav';
            $ffmpeg = env('FFMPEG_PATH', 'ffmpeg');
            $result = Process::run([$ffmpeg, '-y', '-i', $downloadPath, '-ac', '1', '-ar', '16000', '-f', 'wav', $tmpPath]);
            @unlink($downloadPath);

            if (! $result->successful() || ! is_file($tmpPath) || filesize($tmpPath) === 0) {
                Log::warning("TranscribeAndDraftNotes: ffmpeg normalization failed for recording {$this->recordingId}: " . $result->errorOutput());
                throw new RuntimeException('Downloaded recording is not valid/readable audio.');
            }

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
