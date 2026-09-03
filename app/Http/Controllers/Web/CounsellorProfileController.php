<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\CallTargetController;
use App\Models\Extension;
use App\Models\Recording;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// One place to see every counsellor's profile (photo/phone/bio), their call
// target progress, how many tickets they've logged today, and — on
// request — the actual tickets and recordings behind that count.
class CounsellorProfileController extends Controller
{
    private function isManager(Request $request): bool
    {
        return in_array($request->user()->role, ['admin', 'director', 'helpline_manager'], true);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodRange(Request $request): array
    {
        return match ($request->input('period', 'today')) {
            'week'  => [Carbon::today()->startOfWeek(),  Carbon::today()->endOfWeek()],
            'month' => [Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()],
            'year'  => [Carbon::today()->startOfYear(),  Carbon::today()->endOfYear()],
            default => [Carbon::today(),                 Carbon::today()->endOfDay()],
        };
    }

    public function index(Request $request): Response
    {
        abort_unless($this->isManager($request), 403);

        $search = $request->string('search')->trim()->toString();
        $period = $request->input('period', 'today');

        $counsellors = User::where('role', 'agent')
            ->when($search, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->with('callTarget')
            ->orderBy('name')
            ->get();

        [$rangeStart, $rangeEnd] = $this->periodRange($request);

        $ticketsToday = Ticket::whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->whereNotNull('agent_id')
            ->selectRaw('agent_id, count(*) as cnt')
            ->groupBy('agent_id')
            ->pluck('cnt', 'agent_id');

        // calls.agent_id is never populated in practice — the real agent link is
        // calls.extension_number -> extensions.extension_number -> user_id.
        $recordingsToday = Recording::whereBetween('recordings.created_at', [$rangeStart, $rangeEnd])
            ->join('calls', 'calls.id', '=', 'recordings.call_id')
            ->join('extensions', 'extensions.extension_number', '=', 'calls.extension_number')
            ->whereNotNull('extensions.user_id')
            ->selectRaw('extensions.user_id as agent_id, count(*) as cnt')
            ->groupBy('extensions.user_id')
            ->pluck('cnt', 'agent_id');

        $rows = $counsellors->map(function (User $counsellor) use ($ticketsToday, $recordingsToday) {
            $target = CallTargetController::summaryForAgent($counsellor->id);

            return [
                'id'                => $counsellor->id,
                'name'              => $counsellor->name,
                'first_name'        => $counsellor->first_name,
                'surname'           => $counsellor->surname,
                'username'          => $counsellor->username,
                'email'             => $counsellor->email,
                'phone'             => $counsellor->phone,
                'bio'               => $counsellor->bio,
                'avatar'            => $counsellor->avatar,
                'is_active'         => $counsellor->is_active,
                'call_target'       => $target,
                'tickets_today'     => (int) ($ticketsToday[$counsellor->id] ?? 0),
                'recordings_today'  => (int) ($recordingsToday[$counsellor->id] ?? 0),
            ];
        })->values();

        return Inertia::render('Counsellors/Index', [
            'counsellors' => $rows,
            'filters'     => ['search' => $search, 'period' => $period],
        ]);
    }

    // Full profile page for one counsellor — their info, call target, and
    // paginated tickets + recordings for the selected period.
    public function show(Request $request, User $counsellor): Response
    {
        abort_unless($this->isManager($request), 403);
        abort_unless($counsellor->role === 'agent', 404);

        $period = $request->input('period', 'today');
        [$rangeStart, $rangeEnd] = $this->periodRange($request);

        $tickets = Ticket::where('agent_id', $counsellor->id)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->orderByDesc('created_at')
            ->paginate(15, ['id', 'subject', 'contact_number', 'status', 'priority', 'created_at'], 'tickets_page')
            ->withQueryString();

        $extNumber = Extension::where('user_id', $counsellor->id)->value('extension_number');

        $recordings = Recording::whereHas('call', fn ($q) => $q->where('extension_number', $extNumber))
            ->when(! $extNumber, fn ($q) => $q->whereRaw('0 = 1'))
            ->whereBetween('recordings.created_at', [$rangeStart, $rangeEnd])
            ->with('call:id,caller,callee,started_at')
            ->orderByDesc('created_at')
            ->paginate(15, ['id', 'call_id', 'created_at', 'duration', 'transcription_status'], 'recordings_page')
            ->withQueryString();

        return Inertia::render('Counsellors/Show', [
            'counsellor' => [
                'id'          => $counsellor->id,
                'name'        => $counsellor->name,
                'first_name'  => $counsellor->first_name,
                'surname'     => $counsellor->surname,
                'username'    => $counsellor->username,
                'email'       => $counsellor->email,
                'phone'       => $counsellor->phone,
                'bio'         => $counsellor->bio,
                'avatar'      => $counsellor->avatar,
                'is_active'   => $counsellor->is_active,
                'call_target' => CallTargetController::summaryForAgent($counsellor->id),
            ],
            'tickets'    => $tickets,
            'recordings' => $recordings,
            'filters'    => ['period' => $period],
        ]);
    }

    // Drill-down for one counsellor's selected period — fetched on expand, not upfront.
    public function details(Request $request, User $counsellor): JsonResponse
    {
        abort_unless($this->isManager($request), 403);

        [$rangeStart, $rangeEnd] = $this->periodRange($request);

        $tickets = Ticket::where('agent_id', $counsellor->id)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'subject', 'contact_number', 'status', 'priority', 'created_at']);

        $extNumber = Extension::where('user_id', $counsellor->id)->value('extension_number');

        $recordings = Recording::whereHas('call', fn ($q) => $q->where('extension_number', $extNumber))
            ->when(! $extNumber, fn ($q) => $q->whereRaw('0 = 1'))
            ->whereBetween('recordings.created_at', [$rangeStart, $rangeEnd])
            ->with('call:id,caller,started_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'call_id', 'created_at', 'duration', 'transcription_status']);

        return response()->json(['tickets' => $tickets, 'recordings' => $recordings]);
    }
}
