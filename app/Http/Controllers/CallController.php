<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Client;
use App\Services\YeastarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function index(Request $request): JsonResponse
    {
        $query = Call::with(['client', 'agent', 'recording'])
            ->latest('started_at');

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('caller', 'like', "%{$request->search}%")
                  ->orWhere('callee', 'like', "%{$request->search}%")
                  ->orWhereHas('client', fn($c) => $c->where('name', 'like', "%{$request->search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->where('started_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('started_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('extension')) {
            $query->where('extension_number', $request->extension);
        }

        $calls = $query->paginate($request->get('per_page', 25));

        return response()->json($calls);
    }

    public function show(Call $call): JsonResponse
    {
        return response()->json(
            $call->load(['client', 'agent', 'recording', 'ticket', 'callbackQueue.agent'])
        );
    }

    public function sync(Request $request): JsonResponse
    {
        $synced = $this->yeastar->syncCalls(
            $request->get('start_time'),
            $request->get('end_time')
        );

        return response()->json(['synced' => $synced]);
    }

    public function missed(Request $request): JsonResponse
    {
        $calls = Call::with('client')
            ->where('status', 'missed')
            ->latest('started_at')
            ->paginate($request->get('per_page', 25));

        return response()->json($calls);
    }

    public function inbound(Request $request): JsonResponse
    {
        $calls = Call::with('client')
            ->where('direction', 'inbound')
            ->latest('started_at')
            ->paginate($request->get('per_page', 25));

        return response()->json($calls);
    }

    public function outbound(Request $request): JsonResponse
    {
        $calls = Call::with('client')
            ->where('direction', 'outbound')
            ->latest('started_at')
            ->paginate($request->get('per_page', 25));

        return response()->json($calls);
    }

    public function linkClient(Request $request, Call $call): JsonResponse
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);
        $call->update(['client_id' => $request->client_id]);
        return response()->json($call->fresh()->load('client'));
    }

    public function active(): JsonResponse
    {
        $calls = [];

        try {
            $calls = $this->yeastar->getActiveCalls();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Yeastar getActiveCalls failed, using DB fallback', ['error' => $e->getMessage()]);
        }

        // Fallback: inbound calls started in the last 90s that haven't ended
        // yet. Previously filtered on status='answered', but a still-ringing
        // call has no status yet (only set once handleCallEnd runs) — that
        // excluded genuinely active calls. ended_at IS NULL is the correct
        // "still in progress" signal.
        if (empty($calls)) {
            $calls = Call::with('client')
                ->where('direction', 'inbound')
                ->whereNull('ended_at')
                ->where('started_at', '>=', now()->subSeconds(90))
                ->get(['id', 'call_id', 'caller', 'callee', 'extension_number', 'started_at', 'client_id'])
                ->toArray();
        }

        return response()->json(['calls' => $calls]);
    }

    // Calls that lasted >=15s, ended, and still have no ticket logged.
    // Fetched on page load (and re-polled) so the "log a ticket" queue
    // survives a refresh instead of relying purely on the client's
    // in-memory state, which a reload would silently wipe.
    public function needingTicket(Request $request): JsonResponse
    {
        // duration (not ended_at, which the CDR sync leaves null in practice)
        // is the reliable "this call is over" signal here.
        $query = Call::with(['client', 'recording:id,call_id'])
            ->where('duration', '>=', 15)
            ->where('started_at', '>=', now()->subDay())
            ->where('started_at', '<=', now()->subMinute())
            ->whereDoesntHave('ticket')
            ->orderByDesc('started_at');

        // Logging a ticket is the responsibility of whichever agent actually
        // answered the call — not a manager oversight view. Every user
        // (managers included) is scoped to only their own extension's calls.
        $user = $request->user();
        $extNumber = \App\Models\Extension::where('user_id', $user->id)->value('extension_number');
        $extNumber ? $query->where('extension_number', $extNumber) : $query->whereRaw('0 = 1');

        $calls = $query->limit(20)
            ->get(['id', 'call_id', 'caller', 'callee', 'duration', 'direction', 'extension_number', 'started_at', 'client_id'])
            ->map(function (Call $call) {
                return [
                    'id'          => $call->id,
                    'call_id'     => $call->call_id,
                    'caller'      => $call->caller,
                    'callee'      => $call->callee,
                    'duration'    => $call->duration,
                    'direction'   => $call->direction,
                    'started_at'  => $call->started_at,
                    'client'      => $call->client,
                    'recording_id' => $call->recording?->id,
                ];
            });

        return response()->json(['calls' => $calls]);
    }
}
