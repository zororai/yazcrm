<?php

namespace App\Http\Controllers;

use App\Models\CallbackQueue;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackQueueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CallbackQueue::with(['client', 'agent', 'call'])
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderBy('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'phone'        => 'required|string',
            'priority'     => 'in:low,medium,high',
            'scheduled_at' => 'nullable|date',
            'notes'        => 'nullable|string',
            'call_id'      => 'nullable|exists:calls,id',
        ]);

        $client = Client::where('phone', $request->phone)->first();

        $entry = CallbackQueue::create([
            'client_id'    => $client?->id,
            'call_id'      => $request->call_id,
            'phone'        => $request->phone,
            'priority'     => $request->priority ?? 'medium',
            'scheduled_at' => $request->scheduled_at,
            'notes'        => $request->notes,
        ]);

        return response()->json($entry->load(['client', 'call']), 201);
    }

    public function assign(Request $request, CallbackQueue $callbackQueue): JsonResponse
    {
        $request->validate(['agent_id' => 'required|exists:users,id']);

        $callbackQueue->update([
            'agent_id' => $request->agent_id,
            'status'   => 'assigned',
        ]);

        return response()->json($callbackQueue->fresh()->load(['client', 'agent']));
    }

    public function complete(Request $request, CallbackQueue $callbackQueue): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string']);

        $callbackQueue->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'notes'        => $request->notes ?? $callbackQueue->notes,
        ]);

        return response()->json($callbackQueue->fresh());
    }

    public function destroy(CallbackQueue $callbackQueue): JsonResponse
    {
        $callbackQueue->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Callback cancelled.']);
    }
}
