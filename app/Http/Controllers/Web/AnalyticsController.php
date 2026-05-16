<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $days  = (int) $request->get('days', 30);
        $since = now()->subDays($days);

        $overview = [
            'total_calls'     => Call::where('started_at', '>=', $since)->count(),
            'answered_calls'  => Call::where('started_at', '>=', $since)->where('status', 'answered')->count(),
            'missed_calls'    => Call::where('started_at', '>=', $since)->where('status', 'missed')->count(),
            'avg_duration'    => (int) Call::where('started_at', '>=', $since)->where('status', 'answered')->avg('duration'),
            'total_tickets'   => Ticket::where('created_at', '>=', $since)->count(),
            'resolved_tickets'=> Ticket::where('created_at', '>=', $since)->where('status', 'resolved')->count(),
        ];

        $callTrend = Call::select(
                DB::raw('DATE(started_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(status = "answered") as answered'),
                DB::raw('SUM(status = "missed") as missed')
            )
            ->where('started_at', '>=', $since)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $agentPerformance = User::select('users.id', 'users.name')
            ->withCount(['calls as total_calls' => fn ($q) => $q->where('started_at', '>=', $since)])
            ->withCount(['calls as answered_calls' => fn ($q) => $q->where('started_at', '>=', $since)->where('status', 'answered')])
            ->withCount(['calls as missed_calls' => fn ($q) => $q->where('started_at', '>=', $since)->where('status', 'missed')])
            ->withCount(['tickets as open_tickets' => fn ($q) => $q->where('status', 'open')])
            ->where('is_active', true)
            ->orderByDesc('total_calls')
            ->get();

        return Inertia::render('Analytics/Index', [
            'overview'         => $overview,
            'callTrend'        => $callTrend,
            'agentPerformance' => $agentPerformance,
            'days'             => $days,
        ]);
    }
}
