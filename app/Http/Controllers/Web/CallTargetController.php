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
        $agents = User::where('role', 'agent')
            ->where('is_active', true)
            ->with('callTarget')
            ->orderBy('name')
            ->get();

        $today = Carbon::today();

        $rows = $agents->map(function (User $agent) use ($today) {
            $target = $agent->callTarget;

            // Calls made today via this agent's extension
            $todayCount = $this->callsOnDate($agent->id, $today);

            if (!$target) {
                return [
                    'id'            => $agent->id,
                    'name'          => $agent->name,
                    'daily_target'  => null,
                    'start_date'    => null,
                    'today_calls'   => $todayCount,
                    'carry_forward' => 0,
                    'today_required'=> null,
                ];
            }

            $carryForward = $this->carryForward($agent->id, $target->daily_target, $target->start_date, $today);
            $todayRequired = $target->daily_target + $carryForward;

            return [
                'id'             => $agent->id,
                'name'           => $agent->name,
                'daily_target'   => $target->daily_target,
                'start_date'     => $target->start_date->toDateString(),
                'today_calls'    => $todayCount,
                'carry_forward'  => $carryForward,
                'today_required' => $todayRequired,
            ];
        });

        return Inertia::render('CallTargets/Index', [
            'rows' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agent_id'     => 'required|exists:users,id',
            'daily_target' => 'required|integer|min:1|max:9999',
            'start_date'   => 'required|date',
        ]);

        CallTarget::updateOrCreate(
            ['agent_id' => $data['agent_id']],
            ['daily_target' => $data['daily_target'], 'start_date' => $data['start_date']]
        );

        return back()->with('success', 'Target saved.');
    }

    public function destroy(User $user): RedirectResponse
    {
        CallTarget::where('agent_id', $user->id)->delete();
        return back()->with('success', 'Target removed.');
    }

    // ── Agent-facing summary (used by dashboard) ────────────────────────────

    public static function summaryForAgent(int $agentId): array
    {
        $target = CallTarget::where('agent_id', $agentId)->first();
        $today  = Carbon::today();

        $todayCount = (new self)->callsOnDate($agentId, $today);

        if (!$target) {
            return [
                'daily_target'   => null,
                'today_calls'    => $todayCount,
                'carry_forward'  => 0,
                'today_required' => null,
                'remaining'      => null,
            ];
        }

        $carry         = (new self)->carryForward($agentId, $target->daily_target, $target->start_date, $today);
        $todayRequired = $target->daily_target + $carry;
        $remaining     = max(0, $todayRequired - $todayCount);

        return [
            'daily_target'   => $target->daily_target,
            'today_calls'    => $todayCount,
            'carry_forward'  => $carry,
            'today_required' => $todayRequired,
            'remaining'      => $remaining,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function callsOnDate(int $agentId, Carbon $date): int
    {
        return DB::table('calls')
            ->join('extensions', 'calls.extension_number', '=', 'extensions.extension_number')
            ->where('extensions.user_id', $agentId)
            ->whereDate('calls.started_at', $date)
            ->count();
    }

    private function carryForward(int $agentId, int $dailyTarget, Carbon $startDate, Carbon $today): int
    {
        // Get actual call counts per day from start_date up to (not including) today
        $counts = DB::table('calls')
            ->join('extensions', 'calls.extension_number', '=', 'extensions.extension_number')
            ->where('extensions.user_id', $agentId)
            ->where('calls.started_at', '>=', $startDate->startOfDay())
            ->where('calls.started_at', '<', $today->startOfDay())
            ->selectRaw('DATE(calls.started_at) as d, COUNT(*) as cnt')
            ->groupBy('d')
            ->pluck('cnt', 'd');

        // Walk each day; running balance can't go below 0 (surplus clears deficit)
        $balance = 0;
        $period  = CarbonPeriod::create($startDate->toDateString(), $today->copy()->subDay()->toDateString());

        foreach ($period as $day) {
            $made    = (int) ($counts[$day->format('Y-m-d')] ?? 0);
            $balance = max(0, $balance + ($dailyTarget - $made));
        }

        return $balance;
    }
}
