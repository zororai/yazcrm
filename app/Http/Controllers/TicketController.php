<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['client', 'agent', 'call'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json($ticket->load(['client', 'agent', 'call']));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject'     => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_id'   => 'nullable|exists:clients,id',
            'call_id'     => 'nullable|exists:calls,id',
            'priority'    => 'in:low,medium,high,urgent',
        ]);

        $ticket = Ticket::create([
            'call_id'     => $request->call_id,
            'client_id'   => $request->client_id,
            'agent_id'    => $request->user()->id,
            'subject'     => $request->subject,
            'description' => $request->description,
            'priority'    => $request->priority ?? 'medium',
        ]);

        return response()->json($ticket->load(['client', 'agent']), 201);
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'subject'     => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'in:open,in_progress,resolved,closed',
            'priority'    => 'in:low,medium,high,urgent',
            'agent_id'    => 'nullable|exists:users,id',
        ]);

        $data = $request->only(['subject', 'description', 'status', 'priority', 'agent_id']);

        if (isset($data['status']) && $data['status'] === 'resolved' && !$ticket->resolved_at) {
            $data['resolved_at'] = now();
        }

        $ticket->update($data);
        return response()->json($ticket->fresh()->load(['client', 'agent']));
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();
        return response()->json(['message' => 'Ticket deleted.']);
    }
}
