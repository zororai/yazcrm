<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Client;
use App\Models\LookupItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Ticket::with(['client', 'agent'])->latest();

        // Agents only see their own tickets
        if ($request->user()->role !== 'admin') {
            $query->where('agent_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $tickets = $query->paginate(20)->withQueryString();
        $clients = Client::select('id', 'name', 'phone')->orderBy('name')->get();
        $agents  = User::where('is_active', true)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'clients' => $clients,
            'agents'  => $agents,
            'filters' => $request->only(['status', 'priority', 'search']),
            'keyPops'             => LookupItem::where('type', 'key_pops')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'modesOfCommunication' => LookupItem::where('type', 'mode_of_communication')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'projects'             => LookupItem::where('type', 'project')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'servicesRequested'       => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'secondServicesRequested'  => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'servicesRequestedBefore' => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'referredTo'              => LookupItem::where('type', 'referred_to')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
        ]);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        if ($request->user()->role !== 'admin' && $ticket->agent_id !== $request->user()->id) {
            abort(403);
        }

        $ticket->load(['client', 'agent', 'call']);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }

    public function searchNumbers(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) return response()->json([]);

        $numbers = Call::where(function ($query) use ($q) {
                $query->where('caller', 'like', "%{$q}%")
                      ->orWhere('callee', 'like', "%{$q}%");
            })
            ->orderByDesc('started_at')
            ->limit(100)
            ->get(['caller', 'callee'])
            ->flatMap(fn($c) => [$c->caller, $c->callee])
            ->filter(fn($n) => str_contains($n, $q) && $n !== '')
            ->unique()
            ->values()
            ->take(15);

        return response()->json($numbers);
    }

    private array $crmRules = [
        'contact_number'           => 'nullable|string|max:50',
        'follow_up_date'           => 'nullable|date',
        'mode_of_communication'    => 'nullable|string|max:100',
        'call_validity'            => 'nullable|in:valid,invalid',
        'purpose_of_call'          => 'nullable|string|max:255',
        'immediate_action_required' => 'nullable|boolean',
        'caller_age'               => 'nullable|integer|min:1|max:120',
        'caller_gender'            => 'nullable|in:male,female,other,prefer_not_to_say',
        'caller_marital_status'    => 'nullable|string|max:100',
        'key_pops'                 => 'nullable|string|max:255',
        'province'                 => 'nullable|string|max:100',
        'district'                 => 'nullable|string|max:100',
        'location'                 => 'nullable|string|max:255',
        'is_repeat_caller'         => 'nullable|boolean',
        'project'                  => 'nullable|string|max:255',
        'services_requested_before' => 'nullable|string',
        'services_requested'        => 'nullable|string',
        'second_service_requested'  => 'nullable|string|max:255',
        'number_of_services'       => 'nullable|integer|min:0|max:255',
        'referred_to'              => 'nullable|string|max:255',
        'uptake_confirmed'         => 'nullable|boolean',
    ];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject'     => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_id'   => 'nullable|exists:clients,id',
            'call_id'     => 'nullable|exists:calls,id',
            'priority'    => 'in:low,medium,high,urgent',
            ...$this->crmRules,
        ]);

        $ticket = Ticket::create([
            ...$data,
            'agent_id' => $request->user()->id,
            'priority' => $data['priority'] ?? 'medium',
        ]);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket created.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'subject'     => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'in:open,in_progress,resolved,closed',
            'priority'    => 'in:low,medium,high,urgent',
            'agent_id'    => 'nullable|exists:users,id',
            ...$this->crmRules,
        ]);

        if (isset($data['status']) && $data['status'] === 'resolved' && !$ticket->resolved_at) {
            $data['resolved_at'] = now();
        }

        $ticket->update($data);

        return back()->with('success', 'Ticket updated.');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket deleted.');
    }
}
