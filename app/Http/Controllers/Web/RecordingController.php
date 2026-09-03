<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecordingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Recording::with(['call.client:id,name', 'call.agent:id,name', 'call.ticket:id,call_id'])
            ->orderByDesc('created_at');

        // Agents only see recordings of calls to their own assigned extension.
        // Admins, directors, and helpline managers see everything.
        if (! in_array($request->user()->role, ['admin', 'director', 'helpline_manager'], true)) {
            $extNumber = \App\Models\Extension::where('user_id', $request->user()->id)->value('extension_number');
            if ($extNumber) {
                $query->whereHas('call', fn ($q) => $q->where('extension_number', $extNumber));
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('call', function ($q) use ($search) {
                $q->where('caller', 'like', "%{$search}%")
                  ->orWhere('callee', 'like', "%{$search}%")
                  ->orWhere('extension_number', 'like', "%{$search}%");
            });
        }

        $from = $request->input('from');
        $to   = $request->input('to');

        if ($period = $request->input('period')) {
            [$from, $to] = match ($period) {
                'today'   => [Carbon::today(),                 Carbon::today()],
                'week'    => [Carbon::today()->startOfWeek(),  Carbon::today()->endOfWeek()],
                'month'   => [Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()],
                'year'    => [Carbon::today()->startOfYear(),  Carbon::today()->endOfYear()],
                default   => [$from, $to],
            };
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $isManager = in_array($request->user()->role, ['admin', 'director', 'helpline_manager'], true);

        if ($isManager && ($agentId = $request->input('agent_id'))) {
            $query->whereHas('call', fn ($q) => $q->where('agent_id', $agentId));
        }

        $recordings = $query->paginate(20)->withQueryString();

        return Inertia::render('Recordings/Index', [
            'recordings' => $recordings,
            'filters'    => $request->only(['search', 'from', 'to', 'agent_id', 'period']),
            'agents'     => $isManager
                ? \App\Models\User::whereHas('extension')->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }
}
