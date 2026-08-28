<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\TranscribeCall;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\CallTranscript;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallTranscriptController extends Controller
{
    private array $supportedLanguages = ['shona', 'english', 'ndebele'];

    public function store(Request $request, Call $call): RedirectResponse
    {
        $this->authorize('transcribe', $call);

        $data = $request->validate([
            'language' => 'nullable|string|in:' . implode(',', $this->supportedLanguages),
        ]);

        if (! $call->recording) {
            return back()->with('error', 'This call has no recording to transcribe.');
        }

        $existing = CallTranscript::where('call_id', $call->id)->first();
        if ($existing && in_array($existing->status, ['pending', 'processing'], true)) {
            return back()->with('error', 'Transcription is already in progress for this call.');
        }

        CallTranscript::updateOrCreate(
            ['call_id' => $call->id],
            ['status' => 'pending', 'language' => $data['language'] ?? 'shona', 'error_message' => null, 'requested_at' => now()]
        );

        $this->dispatchTranscription($call->id, $data['language'] ?? 'shona');

        return back()->with('success', 'Transcription requested.');
    }

    public function retry(Request $request, Call $call): RedirectResponse
    {
        $this->authorize('transcribe', $call);

        $transcript = CallTranscript::where('call_id', $call->id)->first();
        if (! $transcript || $transcript->status !== 'failed') {
            return back()->with('error', 'Only a failed transcription can be retried.');
        }

        $transcript->update(['status' => 'pending', 'error_message' => null, 'requested_at' => now()]);

        $this->dispatchTranscription($call->id, $transcript->language ?? 'shona');

        return back()->with('success', 'Transcription retry requested.');
    }

    /**
     * Dispatch the transcription job. On QUEUE_CONNECTION=sync (no worker
     * running), this executes inline and can throw — the job already logs
     * and marks the transcript failed before rethrowing, so here we only
     * need to stop that from surfacing as an uncaught 500.
     */
    private function dispatchTranscription(int $callId, string $language): void
    {
        try {
            TranscribeCall::dispatch($callId, $language);
        } catch (\Throwable $e) {
            // Job already logged the failure and marked the transcript row.
        }
    }

    public function markViewed(Request $request, Call $call): RedirectResponse
    {
        $this->authorize('viewTranscript', $call);

        // No-op body — the point of this endpoint is that hitting it (a POST)
        // is picked up by the app-wide audit trail middleware, satisfying the
        // spec's "call.transcript_viewed" event without a bespoke event log.
        return back();
    }

    public function export(Request $request, Call $call): StreamedResponse
    {
        $this->authorize('exportTranscript', $call);

        $transcript = CallTranscript::where('call_id', $call->id)->firstOrFail();

        if ($transcript->status !== 'completed' || empty($transcript->transcript)) {
            abort(404, 'No completed transcript available for this call.');
        }

        // GET requests bypass the app-wide audit middleware (POST/PUT/PATCH/DELETE
        // only), so this event is logged explicitly per spec §17.
        AuditLog::create([
            'user_id'     => $request->user()->id,
            'user_name'   => $request->user()->name,
            'method'      => 'GET',
            'route_name'  => 'calls.transcript.export',
            'path'        => $request->path(),
            'status_code' => 200,
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
        ]);

        $filename = "call-{$call->id}-transcript.txt";
        $body = "Call #{$call->id}\n"
            . "Caller: {$call->caller}\n"
            . "Language: {$transcript->language}\n"
            . "Model: {$transcript->model}\n"
            . 'Transcribed at: ' . optional($transcript->completed_at)->toDateTimeString() . "\n\n"
            . $transcript->transcript;

        return ResponseFacade::streamDownload(function () use ($body) {
            echo $body;
        }, $filename, ['Content-Type' => 'text/plain']);
    }
}
