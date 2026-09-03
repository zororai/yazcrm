<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SpecialDay;
use App\Models\TimetableShift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Generates a rotating day/night roster: for the whole manager-picked date
// range, each agent cycles 14 working days on, 14 days resting, repeating —
// skipping any date they've marked as a special (unavailable) day without
// breaking the cycle — and alternates Day/Night shift in blocks across the
// working days.
class TimetableController extends Controller
{
    private const WORKING_DAYS_TARGET = 14;
    private const REST_DAYS_TARGET    = 14;
    private const DAY_START   = '07:30';
    private const DAY_END     = '17:00';
    private const NIGHT_START = '17:00';
    private const NIGHT_END   = '07:30';

    private function isManager(Request $request): bool
    {
        return in_array($request->user()->role, ['admin', 'director', 'helpline_manager'], true);
    }

    public function index(Request $request): Response
    {
        $user  = $request->user();
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end   = $request->input('end', now()->endOfMonth()->toDateString());
        $isManager = $this->isManager($request);

        $agentQuery = User::where('role', 'agent')->orderBy('name');
        if ($isManager && $agentId = $request->input('agent_id')) {
            $agentQuery->where('id', $agentId);
        } elseif (! $isManager) {
            $agentQuery->where('id', $user->id);
        }
        $agents = $agentQuery->get(['id', 'name', 'username', 'weekly_off_days', 'shift_preference']);
        $agentIds = $agents->pluck('id');

        $shifts = TimetableShift::whereIn('user_id', $agentIds)
            ->whereBetween('work_date', [$start, $end])
            ->get(['user_id', 'work_date', 'shift_type'])
            ->groupBy('user_id');

        $specialDays = SpecialDay::whereIn('user_id', $agentIds)
            ->whereBetween('date', [$start, $end])
            ->get(['id', 'user_id', 'date', 'reason'])
            ->groupBy('user_id');

        $rows = $agents->map(function (User $agent) use ($shifts, $specialDays) {
            return [
                'id'       => $agent->id,
                'name'     => $agent->name,
                'username' => $agent->username,
                'weekly_off_days' => $agent->weekly_off_days ?? [],
                'shift_preference' => $agent->shift_preference ?? 'rotating',
                'shifts'   => ($shifts[$agent->id] ?? collect())->map(fn ($s) => [
                    'date'       => $s->work_date->toDateString(),
                    'shift_type' => $s->shift_type,
                ])->values(),
                'special_days' => ($specialDays[$agent->id] ?? collect())->map(fn ($d) => [
                    'id'     => $d->id,
                    'date'   => $d->date->toDateString(),
                    'reason' => $d->reason,
                ])->values(),
            ];
        })->values();

        return Inertia::render('Timetable/Index', [
            'agents'    => $rows,
            'allAgents' => $isManager ? User::where('role', 'agent')->orderBy('name')->get(['id', 'name', 'weekly_off_days', 'shift_preference']) : [],
            'isManager' => $isManager,
            'filters'   => ['start' => $start, 'end' => $end, 'agent_id' => $request->input('agent_id')],
            'shiftTimes' => [
                'day'   => self::DAY_START . ' – ' . self::DAY_END,
                'night' => self::NIGHT_START . ' – ' . self::NIGHT_END . ' (+1)',
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($this->isManager($request), 403);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'block_size' => 'nullable|integer|min:1|max:60',
            'working_days' => 'nullable|integer|min:1|max:60',
            'rest_days'    => 'nullable|integer|min:0|max:60',
            'agent_ids'  => 'nullable|array',
            'agent_ids.*' => 'integer|exists:users,id',
            'weekly_off'   => 'nullable|array',
            'weekly_off.*' => 'integer|min:0|max:6',
        ]);

        $workingDaysTarget = $validated['working_days'] ?? self::WORKING_DAYS_TARGET;
        $restDaysTarget    = $validated['rest_days'] ?? self::REST_DAYS_TARGET;
        $blockSize = min($validated['block_size'] ?? 1, $workingDaysTarget);
        $label     = Carbon::parse($validated['start_date'])->format('M Y') . ' – ' . Carbon::parse($validated['end_date'])->format('M Y');
        $batchWeeklyOff = $validated['weekly_off'] ?? [];

        // Combines each agent's own persistent weekly-off days (set on
        // their profile) with any weekdays picked just for this batch.
        $agents = User::where('role', 'agent')
            ->when(! empty($validated['agent_ids']), fn ($q) => $q->whereIn('id', $validated['agent_ids']))
            ->get(['id', 'weekly_off_days', 'shift_preference']);

        $allDates = CarbonPeriod::create($validated['start_date'], $validated['end_date'])
            ->toArray();

        DB::transaction(function () use ($agents, $allDates, $validated, $blockSize, $label, $batchWeeklyOff) {
            foreach ($agents as $index => $agent) {
                $weeklyOff = array_unique(array_merge($agent->weekly_off_days ?? [], $batchWeeklyOff));
                // Clear any existing shifts in this range before regenerating.
                TimetableShift::where('user_id', $agent->id)
                    ->whereBetween('work_date', [$validated['start_date'], $validated['end_date']])
                    ->delete();

                $specialDates = SpecialDay::where('user_id', $agent->id)
                    ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
                    ->pluck('date')
                    ->map(fn ($d) => $d->toDateString())
                    ->all();

                $isUnavailableDate = fn (Carbon $d) => in_array($d->toDateString(), $specialDates, true) || in_array($d->dayOfWeek, $weeklyOff, true);
                $validDayCount     = count(array_filter($allDates, fn ($d) => ! $isUnavailableDate($d)));

                // Cycle 14 working days, 14 rest days, repeating across the
                // whole range. A special day (individually marked, or a
                // selected weekly-off weekday) is invisible to both counters
                // — it's just skipped, the cycle picks up right where it
                // left off on the next calendar day.
                //
                // Agents are split into 4 rotating groups so the roster is
                // actually staggered instead of everyone resting/working
                // the same calendar days on the same shift:
                //   group 0: working, starts Day    group 1: working, starts Night
                //   group 2: resting first           group 3: resting first, starts Night once working
                // This guarantees both Day and Night are covered whenever
                // anyone is on duty, and half the team is always resting
                // while the other half covers.
                $group         = $index % 4;
                $mode          = $group >= 2 ? 'resting' : 'working';
                $daysInMode    = 0;
                $workDayIndex  = in_array($group, [1, 3], true) ? $blockSize : 0; // drives Day/Night alternation, never resets
                $rows          = [];

                // Resting-first groups would otherwise burn 14 calendar
                // days before their first working block even starts —
                // in a range that isn't long enough, that leaves too few
                // valid days left to reach 14 real working days. Cap only
                // their FIRST rest phase so at least 14 valid days remain
                // afterward (never below 0); later rest phases (if the
                // range is long enough for a second cycle) use the full
                // 14 as normal.
                $restTarget = $mode === 'resting'
                    ? min(self::REST_DAYS_TARGET, max(0, $validDayCount - self::WORKING_DAYS_TARGET))
                    : self::REST_DAYS_TARGET;

                // Not enough valid days for a meaningful initial rest — skip
                // straight to working so what little range there is gets
                // used towards the 14-day target instead of being wasted.
                if ($mode === 'resting' && $restTarget === 0) {
                    $mode = 'working';
                }

                foreach ($allDates as $date) {
                    $isUnavailable = in_array($date->toDateString(), $specialDates, true) || in_array($date->dayOfWeek, $weeklyOff, true);

                    if ($mode === 'working') {
                        // A special/weekly-off day during a working block
                        // doesn't count towards it — the block simply
                        // extends further into the calendar so the agent
                        // still ends up with exactly 14 real working days.
                        if ($isUnavailable) {
                            continue;
                        }

                        // An agent pinned to Day-only or Night-only skips
                        // the alternation entirely — every working day is
                        // that fixed shift, regardless of block/group.
                        if (in_array($agent->shift_preference, ['day', 'night'], true)) {
                            $shiftType = $agent->shift_preference;
                        } else {
                            $blockIndex = intdiv($workDayIndex, $blockSize);
                            $shiftType  = $blockIndex % 2 === 0 ? 'day' : 'night';
                        }

                        $rows[] = [
                            'user_id'      => $agent->id,
                            'work_date'    => $date->toDateString(),
                            'shift_type'   => $shiftType,
                            'roster_label' => $label,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ];

                        $workDayIndex++;
                        $daysInMode++;

                        if ($daysInMode === self::WORKING_DAYS_TARGET) {
                            $mode       = 'resting';
                            $daysInMode = 0;
                        }
                    } else {
                        // Resting days are never assigned a shift anyway,
                        // so a special/weekly-off day here doesn't need to
                        // extend anything — every calendar day counts,
                        // keeping rest blocks a fixed 14 calendar days and
                        // leaving the range free for working blocks.
                        $daysInMode++;
                        if ($daysInMode === $restTarget) {
                            $mode       = 'working';
                            $daysInMode = 0;
                            $restTarget = self::REST_DAYS_TARGET; // only the first rest phase is capped
                        }
                    }
                }

                if ($rows) {
                    TimetableShift::insert($rows);
                }
            }
        });

        return back()->with('success', 'Timetable generated for ' . $agents->count() . ' agent(s).');
    }

    // ── Weekly off days — each agent's own recurring off weekdays ───────────
    public function updateWeeklyOff(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'      => 'nullable|integer|exists:users,id',
            'weekly_off'   => 'nullable|array',
            'weekly_off.*' => 'integer|min:0|max:6',
        ]);

        $targetId = $validated['user_id'] ?? $request->user()->id;
        abort_unless($targetId === $request->user()->id || $this->isManager($request), 403);

        User::where('id', $targetId)->update(['weekly_off_days' => $validated['weekly_off'] ?? []]);

        return back()->with('success', 'Weekly off days updated.');
    }

    // ── Shift preference — pin an agent to Day-only or Night-only ───────────
    public function updateShiftPreference(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'          => 'nullable|integer|exists:users,id',
            'shift_preference' => 'nullable|in:day,night,rotating',
        ]);

        $targetId = $validated['user_id'] ?? $request->user()->id;
        abort_unless($targetId === $request->user()->id || $this->isManager($request), 403);

        $preference = ($validated['shift_preference'] ?? 'rotating') === 'rotating' ? null : $validated['shift_preference'];
        User::where('id', $targetId)->update(['shift_preference' => $preference]);

        return back()->with('success', 'Shift preference updated.');
    }

    // ── Special (unavailable) days — agents manage their own ────────────────
    public function storeSpecialDay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date'   => 'required|date',
            'reason' => 'nullable|string|max:255',
        ]);

        SpecialDay::updateOrCreate(
            ['user_id' => $request->user()->id, 'date' => $validated['date']],
            ['reason' => $validated['reason'] ?? null],
        );

        return back()->with('success', 'Marked as unavailable.');
    }

    public function destroySpecialDay(Request $request, SpecialDay $specialDay): RedirectResponse
    {
        abort_unless(
            $specialDay->user_id === $request->user()->id || $this->isManager($request),
            403
        );

        $specialDay->delete();

        return back()->with('success', 'Removed.');
    }
}
