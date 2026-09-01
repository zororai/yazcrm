<?php

namespace App\Jobs;

use App\Models\CallAiAnalysis;
use App\Models\CallTranscript;
use App\Services\CaseIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeCallTranscript implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public readonly int $callId)
    {
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(CaseIntelligenceService $intelligence): void
    {
        $transcript = CallTranscript::where('call_id', $this->callId)->first();

        if (! $transcript || $transcript->status !== 'completed' || empty($transcript->transcript)) {
            Log::warning('AnalyzeCallTranscript: no completed transcript for call', ['call_id' => $this->callId]);
            return;
        }

        $analysis = CallAiAnalysis::firstOrNew(['call_id' => $this->callId]);
        $analysis->analysis_status = 'pending';
        $analysis->error_message = null;
        $analysis->save();

        try {
            $result = $intelligence->analyze($transcript->transcript);

            $analysis->update([
                'ai_summary'            => $result['summary'],
                'ai_category'           => $result['category'],
                'ai_priority'           => $result['priority'],
                'ai_follow_up_required' => $result['follow_up_required'],
                'ai_referral_required'  => $result['referral_required'],
                'ai_model'              => $result['model'],
                'status'                => 'pending_review',
                'analysis_status'       => 'completed',
            ]);
        } catch (Throwable $e) {
            // Never log transcript or AI-generated content — failure reason only.
            Log::error('AnalyzeCallTranscript failed', [
                'call_id' => $this->callId,
                'error'   => $e->getMessage(),
            ]);

            $analysis->update([
                'analysis_status' => 'failed',
                'error_message'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        CallAiAnalysis::where('call_id', $this->callId)->update([
            'analysis_status' => 'failed',
            'error_message'   => $exception?->getMessage() ?? 'Analysis failed.',
        ]);
    }
}
