<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallbackQueue;
use App\Models\Client;
use App\Models\Ticket;
use App\Services\YeastarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function index(Request $request): Response
    {
        $user   = $request->user();
        $period = $request->get('period', 'today');
        [$start, $end] = $this->periodRange($period);

        $base = Call::whereBetween('started_at', [$start, $end]);

        // Agents only see their own extension's calls
        $extNumber = null;
        if ($user->role !== 'admin') {
            $extNumber = \App\Models\Extension::where('user_id', $user->id)->value('extension_number');
            if ($extNumber) {
                $base->where('extension_number', $extNumber);
            } else {
                $base->whereRaw('0 = 1');
            }
        }

        $stats = [
            'total_calls'      => (clone $base)->count(),
            'inbound_calls'    => (clone $base)->where('direction', 'inbound')->count(),
            'outbound_calls'   => (clone $base)->where('direction', 'outbound')->count(),
            'missed_calls'     => (clone $base)->where('status', 'missed')->count(),
            'answered_calls'   => (clone $base)->where('status', 'answered')->count(),
            'avg_duration'     => (int)(clone $base)->where('status', 'answered')->avg('duration'),
            'active_clients'   => Client::where('status', 'active')->count(),
            'open_tickets'     => Ticket::where('status', 'open')->count(),
            'callback_pending' => CallbackQueue::where('status', 'pending')->count(),
            'active_calls'     => [],
        ];

        try {
            $stats['active_calls'] = $this->yeastar->getActiveCalls();
        } catch (\Exception) {}

        // ── Previous period for % comparison ─────────────────────────────────
        [$prevStart, $prevEnd] = $this->prevPeriodRange($period);
        $prevBase = Call::whereBetween('started_at', [$prevStart, $prevEnd]);
        if ($extNumber) $prevBase->where('extension_number', $extNumber);
        elseif ($user->role !== 'admin') $prevBase->whereRaw('0 = 1');

        $prevStats = [
            'total_calls'      => (clone $prevBase)->count(),
            'inbound_calls'    => (clone $prevBase)->where('direction', 'inbound')->count(),
            'outbound_calls'   => (clone $prevBase)->where('direction', 'outbound')->count(),
            'missed_calls'     => (clone $prevBase)->where('status', 'missed')->count(),
            'answered_calls'   => (clone $prevBase)->where('status', 'answered')->count(),
            'avg_duration'     => (int)(clone $prevBase)->where('status', 'answered')->avg('duration'),
            'active_clients'   => $stats['active_clients'],
            'open_tickets'     => $stats['open_tickets'],
            'callback_pending' => $stats['callback_pending'],
        ];

        $trendQuery = Call::select(
                DB::raw('DATE(started_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(direction = "inbound") as inbound'),
                DB::raw('SUM(direction = "outbound") as outbound'),
                DB::raw('SUM(status = "missed") as missed')
            )
            ->where('started_at', '>=', now()->subDays(7));

        if ($extNumber) {
            $trendQuery->where('extension_number', $extNumber);
        }

        $callTrend = $trendQuery->groupBy('date')->orderBy('date')->get();

        $extQuery = Call::select('extension_number', DB::raw('COUNT(*) as total'))
            ->whereNotNull('extension_number')
            ->where('started_at', '>=', now()->subDays(30));

        if ($extNumber) {
            $extQuery->where('extension_number', $extNumber);
        }

        $topExtensions = $extQuery->groupBy('extension_number')->orderByDesc('total')->limit(10)->get();

        $targetSummary = $request->user()->role !== 'admin'
            ? CallTargetController::summaryForAgent($request->user()->id)
            : null;

        // ── Recent calls for activity feed ────────────────────────────────────
        $recentQuery = Call::select('id', 'caller_number', 'extension_number', 'direction', 'status', 'duration', 'started_at')
            ->orderByDesc('started_at')
            ->limit(6);
        if ($extNumber) $recentQuery->where('extension_number', $extNumber);
        elseif ($user->role !== 'admin') $recentQuery->whereRaw('0 = 1');
        $recentCalls = $recentQuery->get();

        return Inertia::render('Dashboard/Index', [
            'stats'         => $stats,
            'prevStats'     => $prevStats,
            'callTrend'     => $callTrend,
            'topExtensions' => $topExtensions,
            'period'        => $period,
            'targetSummary' => $targetSummary,
            'extension'     => $extNumber,
            'recentCalls'   => $recentCalls,
        ]);
    }

    private function periodRange(string $period): array
    {
        return match ($period) {
            'week'  => [now()->startOfWeek(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function prevPeriodRange(string $period): array
    {
        return match ($period) {
            'week'  => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            default => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
        };
    }
}
