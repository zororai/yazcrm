<?php

namespace App\Http\Controllers;

use App\Events\CallEndedEvent;
use App\Events\IncomingCallEvent;
use App\Models\Call;
use App\Models\CallbackQueue;
use App\Models\Client;
use App\Models\Extension;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

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

        $client = Client::where('phone', $caller)->first();

        $callData = [
            'call_id'  => $payload['call_id'] ?? uniqid('live_'),
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
            'ANSWERED'  => 'answered',
            'BUSY'      => 'busy',
            'FAILED'    => 'failed',
            default     => 'missed',
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

        // Prompt agents to log a ticket for any answered call that lasted ≥ 30 s
        if ($status === 'answered' && $duration >= 30) {
            event(new CallEndedEvent($call->load('client')));
        }

        if ($status === 'missed') {
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
