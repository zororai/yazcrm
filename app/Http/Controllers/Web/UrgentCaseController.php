<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\UrgentCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UrgentCaseController extends Controller
{
    public function index(): Response
    {
        $cases = UrgentCase::with(['agent', 'resolvedBy', 'sourceTicket', 'createdTicket'])
            ->orderByRaw("FIELD(status, 'open', 'resolved')")
            ->orderByDesc('created_at')
            ->paginate(30);

        return Inertia::render('UrgentCases/Index', ['cases' => $cases]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject'        => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'description'    => 'nullable|string',
        ]);

        UrgentCase::create([
            ...$data,
            'agent_id' => auth()->id(),
            'status'   => 'open',
        ]);

        return back()->with('success', 'Urgent case logged.');
    }

    public function resolve(UrgentCase $urgentCase): RedirectResponse
    {
        $urgentCase->update([
            'status'         => 'resolved',
            'resolved_at'    => now(),
            'resolved_by_id' => auth()->id(),
        ]);

        return back()->with('success', 'Case marked as resolved.');
    }

    /** Create a ticket from an urgent case that has no source ticket yet. */
    public function createTicket(UrgentCase $urgentCase): RedirectResponse
    {
        $ticket = Ticket::create([
            'agent_id'                  => auth()->id(),
            'subject'                   => $urgentCase->subject,
            'contact_number'            => $urgentCase->contact_number,
            'description'               => $urgentCase->description,
            'status'                    => 'open',
            'priority'                  => 'urgent',
            'immediate_action_required' => true,
        ]);

        $urgentCase->update(['created_ticket_id' => $ticket->id]);

        return redirect("/tickets/{$ticket->id}")->with('success', 'Ticket created from urgent case.');
    }
}
