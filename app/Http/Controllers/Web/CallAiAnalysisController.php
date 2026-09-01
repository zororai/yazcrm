<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCallTranscript;
use App\Models\Call;
use App\Models\CallAiAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CallAiAnalysisController extends Controller
{
    public function store(Request $request, Call $call): RedirectResponse
    {
        $this->authorize('reviewAiAnalysis', $call);

        if (! $call->transcript || $call->transcript->status !== 'completed') {
            return back()->with('error', 'A completed transcript is required before generating an AI summary.');
        }

        $existing = CallAiAnalysis::where('call_id', $call->id)->first();
        if ($existing && $existing->analysis_status === 'pending') {
            return back()->with('error', 'Analysis is already in progress for this call.');
        }

        try {
            AnalyzeCallTranscript::dispatch($call->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to queue AI analysis: ' . $e->getMessage());
        }

        return back()->with('success', 'AI analysis requested.');
    }

    public function review(Request $request, Call $call): RedirectResponse
    {
        $this->authorize('reviewAiAnalysis', $call);

        $analysis = CallAiAnalysis::where('call_id', $call->id)->firstOrFail();

        $data = $request->validate([
            'action'           => 'required|in:accept,edit,reject',
            'reviewed_summary' => 'required_if:action,edit|nullable|string',
        ]);

        // The AI-generated summary (ai_summary) is never touched here — only
        // the human-reviewed copy is written, so the record always shows
        // both, distinguishing AI output from human-confirmed content (§19).
        $analysis->update([
            'status'           => $data['action'] === 'edit' ? 'edited' : ($data['action'] === 'reject' ? 'rejected' : 'accepted'),
            'reviewed_summary' => $data['action'] === 'edit' ? $data['reviewed_summary'] : ($data['action'] === 'accept' ? $analysis->ai_summary : null),
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
        ]);

        return back()->with('success', 'Review recorded.');
    }
}
