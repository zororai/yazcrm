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
        } catch (\Exception) {}

        // Fallback: inbound calls started in last 90 seconds not yet ended
        if (empty($calls)) {
            $calls = Call::with('client')
                ->where('direction', 'inbound')
                ->where('status', 'answered')
                ->where('started_at', '>=', now()->subSeconds(90))
                ->get(['id', 'caller', 'callee', 'extension_number', 'started_at', 'client_id'])
                ->toArray();
        }

        return response()->json(['calls' => $calls]);
    }
}
