<?php

namespace App\Jobs;

use App\Models\Call;
use App\Models\CallTranscript;
use App\Services\SpeechToTextService;
use App\Services\YeastarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranscribeCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;

    public function __construct(
        public readonly int $callId,
        public readonly string $language = 'shona',
    ) {
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(SpeechToTextService $speechToText, YeastarService $yeastar): void
    {
        $call = Call::with('recording')->find($this->callId);

        if (! $call) {
            Log::warning('TranscribeCall: call not found', ['call_id' => $this->callId]);
            return;
        }

        if (! $call->recording) {
            Log::warning('TranscribeCall: no recording for call', ['call_id' => $this->callId]);
            $this->markFailed($call, 'No recording exists for this call.');
            return;
        }

        $transcript = CallTranscript::firstOrNew(['call_id' => $call->id]);
        $transcript->fill([
            'language'     => $this->language,
            'requested_at' => $transcript->requested_at ?? now(),
        ]);
        $transcript->status = 'processing';
        $transcript->error_message = null;
        $transcript->save();

        $tmpPath = null;

        try {
            $url = $call->recording->file_url
                ?? $yeastar->getRecordingDownloadUrl($call->recording->file_name);

            if (! $url) {
                throw new \RuntimeException('Recording download URL unavailable.');
            }

            $response = Http::withoutVerifying()->timeout(120)->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException("Failed to download recording (HTTP {$response->status()}).");
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'call_') . '.wav';
            file_put_contents($tmpPath, $response->body());

            $result = $speechToText->transcribe($tmpPath, $this->language);

            $transcript->update([
                'language'            => $result['language'],
                'model'               => $result['model'],
                'transcript'          => $result['transcript'],
                'confidence'          => $result['confidence'],
                'processing_time_ms'  => $result['processing_time_ms'],
                'status'              => 'completed',
                'error_message'       => null,
                'completed_at'        => now(),
            ]);

            if (config('asr.ai_intelligence.enabled')) {
                try {
                    AnalyzeCallTranscript::dispatch($call->id);
                } catch (Throwable $e) {
                    Log::error('AnalyzeCallTranscript dispatch failed', ['call_id' => $call->id, 'error' => $e->getMessage()]);
                }
            }
        } catch (Throwable $e) {
            // Never log transcript content — only the failure reason.
            Log::error('TranscribeCall failed', [
                'call_id' => $this->callId,
                'attempt' => $this->attempts(),
                'error'   => $e->getMessage(),
            ]);

            $this->markFailed($transcript, $e->getMessage());

            throw $e;
        } finally {
            if ($tmpPath && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $transcript = CallTranscript::where('call_id', $this->callId)->first();
        if ($transcript) {
            $this->markFailed($transcript, $exception?->getMessage() ?? 'Transcription failed.');
        }
    }

    private function markFailed(CallTranscript|Call $target, string $reason): void
    {
        if ($target instanceof Call) {
            $target = CallTranscript::firstOrNew(['call_id' => $target->id]);
        }

        $target->status = 'failed';
        $target->error_message = $reason;
        $target->save();
    }
}
