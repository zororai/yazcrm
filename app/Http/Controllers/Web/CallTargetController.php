<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CallTarget;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CallTargetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('CallTargets/Index', [
            'rows' => $this->buildRows(),
        ]);
    }

    public static function allRows(): array
    {
        return (new self)->buildRows();
    }

    private function buildRows(): array
    {
        $agents = User::where('role', 'agent')
            ->where('is_active', true)
            ->with('callTarget')
            ->orderBy('name')
            ->get();

        $today = Carbon::today();

        return $agents->map(function (User $agent) use ($today) {
            $target = $agent->callTarget;

            $todayCount = $this->callsOnDate($agent->id, $today);

            if (!$target) {
                return [
                    'id'             => $agent->id,
                    'name'           => $agent->name,
                    'daily_target'   => null,
                    'start_date'     => null,
                    'end_date'       => null,
                    'target_day'     => null,
                    'span_days'      => null,
                    'period_days'    => null,
                    'today_calls'    => $todayCount,
                    'carry_forward'  => 0,
                    'today_required' => null,
                    'expired'        => false,
                    'period_target'  => null,
                    'period_calls'   => null,
                ];
            }

            $expired      = $target->end_date && $target->end_date->lt($today);
            $carryForward = $expired ? 0 : $this->carryForward(
                $agent->id, $target->daily_target, $target->start_date, $target->end_date, $today
            );
            $todayRequired = $expired ? null : $target->daily_target + $carryForward;

            // Total span in days (start → end/today) — used to constrain the Day input
            $spanEnd  = $target->end_date ?? $today;
            $spanDays = $target->start_date->diffInDays($spanEnd) + 1;

            [$periodTarget, $periodCalls, $periodDays] = $this->periodTotals(
                $agent->id, $target->daily_target, $target->start_date, $target->end_date, $today, $target->target_day
            );

            return [
                'id'             => $agent->id,
                'name'           => $agent->name,
                'daily_target'   => $target->daily_target,
                'start_date'     => $target->start_date->toDateString(),
                'end_date'       => $target->end_date?->toDateString(),
                'target_day'     => $target->target_day,      // integer
                'span_days'      => (int) $spanDays,          // max allowed value for Day input
                'period_days'    => $periodDays,
                'today_calls'    => $todayCount,
                'carry_forward'  => $carryForward,
                'today_required' => $todayRequired,
                'expired'        => $expired,
                'period_target'  => $periodTarget,
                'period_calls'   => $periodCalls,
            ];
        })->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agent_id'     => 'required|exists:users,id',
            'daily_target' => 'required|integer|min:1|max:9999',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'target_day'   => 'nullable|integer|min:1',
        ]);

        CallTarget::updateOrCreate(
            ['agent_id' => $data['agent_id']],
            [
                'daily_target' => $data['daily_target'],
                'start_date'   => $data['start_date'],
                'end_date'     => $data['end_date'] ?? null,
                'target_day'   => $data['target_day'] ?? null,
            ]
        );

        return back()->with('success', 'Target saved.');
    }

    public function destroy(User $user): RedirectResponse
    {
        CallTarget::where('agent_id', $user->id)->delete();
        return back()->with('success', 'Target removed.');
    }

    // ── Agent dashboard summary ───────────────────────────────────────────────

    public static function summaryForAgent(int $agentId): array
    {
        $target = CallTarget::where('agent_id', $agentId)->first();
        $today  = Carbon::today();

        $todayCount = (new self)->callsOnDate($agentId, $today);

        if (!$target) {
            return ['daily_target' => null, 'today_calls' => $todayCount,
                    'carry_forward' => 0, 'today_required' => null, 'remaining' => null,
                    'start_date' => null, 'end_date' => null, 'expired' => false];
        }

        $expired = $target->end_date && $target->end_date->lt($today);

        if ($expired) {
            return ['daily_target' => $target->daily_target, 'today_calls' => $todayCount,
                    'carry_forward' => 0, 'today_required' => null, 'remaining' => null,
                    'start_date' => $target->start_date->toDateString(),
                    'end_date'   => $target->end_date->toDateString(), 'expired' => true];
        }

        $carry         = (new self)->carryForward($agentId, $target->daily_target, $target->start_date, $target->end_date, $today);
        $todayRequired = $target->daily_target + $carry;
        $remaining     = max(0, $todayRequired - $todayCount);

        return [
            'daily_target'   => $target->daily_target,
            'today_calls'    => $todayCount,
            'carry_forward'  => $carry,
            'today_required' => $todayRequired,
            'remaining'      => $remaining,
            'start_date'     => $target->start_date->toDateString(),
            'end_date'       => $target->end_date?->toDateString(),
            'expired'        => false,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Returns [total_target_for_period, total_calls_made_in_period, days_used] */
    private function periodTotals(int $agentId, int $dailyTarget, Carbon $startDate, ?Carbon $endDate, Carbon $today, ?int $targetDays = null): array
    {
        if ($targetDays !== null && $targetDays >= 1) {
            // User specified a day count — clamp to the actual period span
            $maxSpan   = $endDate ? ($startDate->diffInDays($endDate) + 1) : null;
            $totalDays = $maxSpan !== null ? min($targetDays, $maxSpan) : $targetDays;
            // The end of the counting window = start + (totalDays - 1)
            $countEnd  = $startDate->copy()->addDays($totalDays - 1);
        } else {
            // No day set — use end_date or today
            $countEnd  = $endDate ?? $today;
            $totalDays = max(0, $startDate->diffInDays($countEnd) + 1);
        }

        $periodTarget = $dailyTarget * $totalDays;

        // Count calls from start up to min(today, countEnd)
        $countUpTo = $countEnd->gt($today) ? $today : $countEnd;

        $periodCalls = DB::table('calls')
            ->join('extensions', 'calls.extension_number', '=', 'extensions.extension_number')
            ->where('extensions.user_id', $agentId)
            ->where('calls.started_at', '>=', $startDate->copy()->startOfDay())
            ->where('calls.started_at', '<=', $countUpTo->copy()->endOfDay())
            ->count();

        return [$periodTarget, $periodCalls, (int) $totalDays];
    }

    private function callsOnDate(int $agentId, Carbon $date): int
    {
        return DB::table('calls')
            ->join('extensions', 'calls.extension_number', '=', 'extensions.extension_number')
            ->where('extensions.user_id', $agentId)
            ->whereDate('calls.started_at', $date)
            ->count();
    }

    private function carryForward(int $agentId, int $dailyTarget, Carbon $startDate, ?Carbon $endDate, Carbon $today): int
    {
        // Upper bound: yesterday, or end_date if it's in the past — whichever is earlier
        $upperBound = $today->copy()->subDay();
        if ($endDate && $endDate->lt($upperBound)) {
            $upperBound = $endDate->copy();
        }

        if ($upperBound->lt($startDate)) {
            return 0; // period hasn't started yet
        }

        $counts = DB::table('calls')
            ->join('extensions', 'calls.extension_number', '=', 'extensions.extension_number')
            ->where('extensions.user_id', $agentId)
            ->where('calls.started_at', '>=', $startDate->copy()->startOfDay())
            ->where('calls.started_at', '<', $today->copy()->startOfDay())
            ->selectRaw('DATE(calls.started_at) as d, COUNT(*) as cnt')
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $balance = 0;
        $period  = CarbonPeriod::create($startDate->toDateString(), $upperBound->toDateString());

        foreach ($period as $day) {
            $made    = (int) ($counts[$day->format('Y-m-d')] ?? 0);
            $balance = max(0, $balance + ($dailyTarget - $made));
        }

        return $balance;
    }
}
