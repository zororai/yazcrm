<?php

namespace App\Http\Controllers;

use App\Models\Call;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $days = (int)$request->get('days', 30);
        $from = now()->subDays($days);

        $hourly = Call::select(
                DB::raw('HOUR(started_at) as hour'),
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(duration) as avg_duration')
            )
            ->where('started_at', '>=', $from)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $byDirection = Call::select('direction', DB::raw('COUNT(*) as total'))
            ->where('started_at', '>=', $from)
            ->groupBy('direction')
            ->get();

        $byStatus = Call::select('status', DB::raw('COUNT(*) as total'))
            ->where('started_at', '>=', $from)
            ->groupBy('status')
            ->get();

        $avgHandleTime = Call::where('status', 'answered')
            ->where('started_at', '>=', $from)
            ->avg('duration');

        $missedRate = Call::where('started_at', '>=', $from)->count();
        $missedCount = Call::where('status', 'missed')->where('started_at', '>=', $from)->count();

        return response()->json([
            'hourly_distribution' => $hourly,
            'by_direction'        => $byDirection,
            'by_status'           => $byStatus,
            'avg_handle_time'     => round((float)$avgHandleTime),
            'missed_rate'         => $missedRate > 0 ? round($missedCount / $missedRate * 100, 1) : 0,
        ]);
    }

    public function agentPerformance(Request $request): JsonResponse
    {
        $days = (int)$request->get('days', 30);

        $data = Call::select(
                'agent_id',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(status = "answered") as answered'),
                DB::raw('AVG(duration) as avg_duration')
            )
            ->whereNotNull('agent_id')
            ->where('started_at', '>=', now()->subDays($days))
            ->groupBy('agent_id')
            ->with('agent:id,name')
            ->get();

        return response()->json($data);
    }
}
