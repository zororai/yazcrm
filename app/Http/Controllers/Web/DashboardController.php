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
        $period = $request->get('period', 'today');
        [$start, $end] = $this->periodRange($period);
        $base = Call::whereBetween('started_at', [$start, $end]);

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

        $callTrend = Call::select(
                DB::raw('DATE(started_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(direction = "inbound") as inbound'),
                DB::raw('SUM(direction = "outbound") as outbound'),
                DB::raw('SUM(status = "missed") as missed')
            )
            ->where('started_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topExtensions = Call::select('extension_number', DB::raw('COUNT(*) as total'))
            ->whereNotNull('extension_number')
            ->where('started_at', '>=', now()->subDays(30))
            ->groupBy('extension_number')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats'         => $stats,
            'callTrend'     => $callTrend,
            'topExtensions' => $topExtensions,
            'period'        => $period,
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
