<?php

namespace App\Http\Controllers;

use App\Events\CallEndedEvent;
use App\Events\IncomingCallEvent;
use App\Jobs\TranscribeAndDraftNotes;
use App\Jobs\TranscribeCall;
use App\Models\Call;
use App\Models\CallbackQueue;
use App\Models\CallTranscript;
use App\Models\Client;
use App\Models\Extension;
use App\Models\Recording;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(private WhatsAppService $whatsApp) {}

    public function yeastar(Request $request): Response
    {
        $payload = $request->all();
        Log::info('Yeastar webhook', $payload);

        $event = $payload['event'] ?? $payload['type'] ?? '';

        match ($event) {
            'call_start', 'RING'    => $this->handleCallStart($payload),
            'call_end', 'HANGUP'   => $this->handleCallEnd($payload),
            'extension_status'     => $this->handleExtensionStatus($payload),
            default                => null,
        };

        return response('OK', 200);
    }

    public function whatsapp(Request $request): Response
    {
        $message = $this->whatsApp->handleIncoming($request->all());
        Log::info('WhatsApp incoming', ['message_id' => $message->id]);
        return response('OK', 200);
    }

    private function handleCallStart(array $payload): void
    {
        $caller = $payload['caller'] ?? $payload['src'] ?? '';
        $callee = $payload['callee'] ?? $payload['dst'] ?? '';
        $callId = $payload['call_id'] ?? uniqid('live_');

        $client = Client::where('phone', $caller)->first();

        // Persist a lightweight row immediately so the incoming-call popup's
        // polling fallback (used when the live Yeastar active-call API is
        // unavailable) has an accurate record of calls that are ringing
        // right now — not just calls that have already ended. handleCallEnd()
        // upserts by call_id, so this is safely overwritten/completed later.
        // 'status' is left at its DB default ('missed') here — the enum has
        // no "ringing" state — and 'ended_at' stays null. handleCallEnd()
        // fills in the real status once the call is over. In the meantime,
        // "started but ended_at still null" is what marks a call as live.
        Call::updateOrCreate(
            ['call_id' => $callId],
            [
                'caller'           => $caller,
                'callee'           => $callee,
                'direction'        => 'inbound',
                'started_at'       => now(),
                'extension_number' => $payload['extension'] ?? null,
                'client_id'        => $client?->id,
            ]
        );

        $callData = [
            'call_id'  => $callId,
            'caller'   => $caller,
            'callee'   => $callee,
            'direction' => 'inbound',
            'client'   => $client?->only(['id', 'name', 'phone', 'company']),
            'timestamp' => now()->toISOString(),
        ];

        event(new IncomingCallEvent($callData));
    }

    private function handleCallEnd(array $payload): void
    {
        $callId     = $payload['call_id'] ?? '';
        $disposition = $payload['disposition'] ?? 'NOANSWER';
        $duration   = (int)($payload['duration'] ?? 0);

        $status = match (strtoupper($disposition)) {
            'ANSWERED'                      => 'answered',
            'BUSY'                          => 'busy',
            'FAILED', 'CONGESTION',
            'CHANUNAVAIL'                   => 'failed',
            'NOANSWER', 'NO ANSWER',
            'CANCEL', 'CANCELLED'           => 'missed',
            default                         => 'failed',
        };

        $call = Call::where('call_id', $callId)->first();

        if (!$call) {
            $caller = $payload['caller'] ?? $payload['src'] ?? '';
            $client = Client::where('phone', $caller)->first();

            $call = Call::create([
                'call_id'          => $callId,
                'caller'           => $caller,
                'callee'           => $payload['callee'] ?? $payload['dst'] ?? '',
                'direction'        => 'inbound',
                'status'           => $status,
                'duration'         => $duration,
                'started_at'       => $payload['start_time'] ?? now()->subSeconds($duration),
                'ended_at'         => now(),
                'extension_number' => $payload['extension'] ?? null,
                'client_id'        => $client?->id,
                'raw_data'         => $payload,
            ]);
        } else {
            $call->update([
                'status'    => $status,
                'duration'  => $duration,
                'ended_at'  => now(),
                'raw_data'  => $payload,
            ]);
        }

        // Prompt agents to log a ticket for any answered call that lasted ≥ 15 s
        if ($status === 'answered' && $duration >= 15) {
            event(new CallEndedEvent($call->load('client')));
        }

        // If Yeastar included a recording file, create Recording and queue transcription
        $recordFile = $payload['recordfile'] ?? $payload['record_file'] ?? $payload['recording'] ?? null;
        if ($recordFile && $status === 'answered') {
            $recording = Recording::firstOrCreate(
                ['call_id' => $call->id],
                [
                    'file_name' => basename($recordFile),
                    'file_path' => $recordFile,
                    'duration'  => $duration,
                    'format'    => 'wav',
                ]
            );
            TranscribeAndDraftNotes::dispatch($recording->id)->onQueue('default');

            // Multilingual ASR pipeline — opt-in alongside the existing
            // Whisper-based one above. See config/asr.php for why this is
            // off by default (blocking risk under QUEUE_CONNECTION=sync).
            if (config('asr.auto_transcribe.enabled') && ! CallTranscript::where('call_id', $call->id)->exists()) {
                try {
                    TranscribeCall::dispatch($call->id, config('asr.auto_transcribe.default_language', 'shona'));
                } catch (Throwable $e) {
                    Log::error('Auto-transcription dispatch failed', ['call_id' => $call->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // Only queue a callback for inbound missed calls — not outbound or internal
        if ($status === 'missed' && $call->direction === 'inbound') {
            CallbackQueue::firstOrCreate(
                ['call_id' => $call->id, 'status' => 'pending'],
                [
                    'client_id' => $call->client_id,
                    'phone'     => $call->caller,
                    'priority'  => 'high',
                ]
            );
        }
    }

    private function handleExtensionStatus(array $payload): void
    {
        $number = $payload['extension'] ?? '';
        $status = strtolower($payload['status'] ?? 'idle');

        Extension::where('extension_number', $number)
            ->update(['status' => $status]);
    }
}
