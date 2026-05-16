<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\CallbackQueue;
use App\Models\Client;
use App\Models\Ticket;
use App\Services\YeastarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function stats(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        [$start, $end] = $this->getPeriodRange($period);

        $base = Call::whereBetween('started_at', [$start, $end]);

        $stats = [
            'total_calls'    => (clone $base)->count(),
            'inbound_calls'  => (clone $base)->where('direction', 'inbound')->count(),
            'outbound_calls' => (clone $base)->where('direction', 'outbound')->count(),
            'missed_calls'   => (clone $base)->where('status', 'missed')->count(),
            'answered_calls' => (clone $base)->where('status', 'answered')->count(),
            'avg_duration'   => (int)(clone $base)->where('status', 'answered')->avg('duration'),
            'active_clients' => Client::where('status', 'active')->count(),
            'open_tickets'   => Ticket::where('status', 'open')->count(),
            'callback_pending' => CallbackQueue::where('status', 'pending')->count(),
            'active_calls'   => [],
        ];

        try {
            $stats['active_calls'] = $this->yeastar->getActiveCalls();
        } catch (\Exception $e) {
            // PBX might be unreachable; non-fatal
        }

        return response()->json($stats);
    }

    public function callTrend(Request $request): JsonResponse
    {
        $days = (int)$request->get('days', 7);

        $trend = Call::select(
                DB::raw('DATE(started_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(direction = "inbound") as inbound'),
                DB::raw('SUM(direction = "outbound") as outbound'),
                DB::raw('SUM(status = "missed") as missed')
            )
            ->where('started_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($trend);
    }

    public function topExtensions(Request $request): JsonResponse
    {
        $data = Call::select('extension_number', DB::raw('COUNT(*) as total'))
            ->whereNotNull('extension_number')
            ->where('started_at', '>=', now()->subDays(30))
            ->groupBy('extension_number')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json($data);
    }

    private function getPeriodRange(string $period): array
    {
        return match ($period) {
            'week'  => [now()->startOfWeek(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }
}
