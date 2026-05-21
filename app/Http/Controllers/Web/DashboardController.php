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

        return Inertia::render('Dashboard/Index', [
            'stats'         => $stats,
            'callTrend'     => $callTrend,
            'topExtensions' => $topExtensions,
            'period'        => $period,
            'targetSummary' => $targetSummary,
            'extension'     => $extNumber,
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
}
