<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PriorityAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriorityActionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PriorityAction::with(['risk.asset'])
            ->orderByRaw("FIELD(priority,'critical','high','medium','low')")
            ->orderByRaw("FIELD(status,'open','in_progress','done')")
            ->orderBy('target_date');

        if ($request->filled('status'))   { $query->where('status', $request->status); }
        if ($request->filled('priority')) { $query->where('priority', $request->priority); }

        $actions = $query->paginate(25)->withQueryString();

        $actions->through(function ($action) {
            $action->is_overdue = $action->status !== 'done'
                && $action->target_date
                && $action->target_date->isPast();
            return $action;
        });

        return Inertia::render('Risk/Actions', [
            'actions' => $actions,
            'filters' => $request->only(['status', 'priority']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'risk_id'     => 'required|exists:risks,id',
            'action_ref'  => 'required|string|max:100|unique:priority_actions,action_ref',
            'description' => 'required|string',
            'owner'       => 'required|string|max:255',
            'target_date' => 'required|date',
            'status'      => 'required|in:open,in_progress,done',
            'priority'    => 'required|in:low,medium,high,critical',
        ]);

        if ($data['status'] === 'done') {
            $data['completed_at'] = now();
        }

        PriorityAction::create($data);

        return back()->with('success', 'Action added.');
    }

    public function update(Request $request, PriorityAction $action): RedirectResponse
    {
        $data = $request->validate([
            'risk_id'     => 'sometimes|exists:risks,id',
            'action_ref'  => 'sometimes|string|max:100|unique:priority_actions,action_ref,' . $action->id,
            'description' => 'sometimes|string',
            'owner'       => 'sometimes|string|max:255',
            'target_date' => 'sometimes|date',
            'status'      => 'sometimes|in:open,in_progress,done',
            'priority'    => 'sometimes|in:low,medium,high,critical',
        ]);

        if (isset($data['status']) && $data['status'] === 'done' && !$action->completed_at) {
            $data['completed_at'] = now();
        }

        $action->update($data);

        return back()->with('success', 'Action updated.');
    }

    public function destroy(PriorityAction $action): RedirectResponse
    {
        $action->delete();

        return back()->with('success', 'Action deleted.');
    }
}
