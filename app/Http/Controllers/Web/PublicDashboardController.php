<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PublicDashboardController extends Controller
{
    public function index()
    {
        $base = DB::table('tickets')->whereNull('deleted_at');

        $total        = (clone $base)->count();
        $validTotal   = (clone $base)->where('call_validity', 'valid')->count();
        $repeatTotal  = (clone $base)->where('is_repeat_caller', true)->count();
        $uptakeTotal  = (clone $base)->where('uptake_confirmed', true)->count();
        $immediateAct = (clone $base)->where('immediate_action_required', true)->count();

        // Status breakdown
        $byStatus = (clone $base)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // Province breakdown
        $byProvince = (clone $base)
            ->whereNotNull('province')->where('province', '!=', '')
            ->select('province', DB::raw('count(*) as cnt'))
            ->groupBy('province')
            ->orderByDesc('cnt')
            ->get();

        // Gender breakdown
        $byGender = (clone $base)
            ->whereNotNull('caller_gender')->where('caller_gender', '!=', '')
            ->select('caller_gender', DB::raw('count(*) as cnt'))
            ->groupBy('caller_gender')
            ->orderByDesc('cnt')
            ->get();

        // Mode of communication
        $byMode = (clone $base)
            ->whereNotNull('mode_of_communication')->where('mode_of_communication', '!=', '')
            ->select('mode_of_communication', DB::raw('count(*) as cnt'))
            ->groupBy('mode_of_communication')
            ->orderByDesc('cnt')
            ->get();

        // Purpose of call (top 10)
        $byPurpose = (clone $base)
            ->whereNotNull('purpose_of_call')->where('purpose_of_call', '!=', '')
            ->select('purpose_of_call', DB::raw('count(*) as cnt'))
            ->groupBy('purpose_of_call')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // Services requested (top 10)
        $byService = (clone $base)
            ->whereNotNull('services_requested')->where('services_requested', '!=', '')
            ->select('services_requested', DB::raw('count(*) as cnt'))
            ->groupBy('services_requested')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // Referred to (top 8)
        $byReferral = (clone $base)
            ->whereNotNull('referred_to')->where('referred_to', '!=', '')
            ->select('referred_to', DB::raw('count(*) as cnt'))
            ->groupBy('referred_to')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        // Key pops (top 8)
        $byKeyPops = (clone $base)
            ->whereNotNull('key_pops')->where('key_pops', '!=', '')
            ->select('key_pops', DB::raw('count(*) as cnt'))
            ->groupBy('key_pops')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        // Marital status
        $byMarital = (clone $base)
            ->whereNotNull('caller_marital_status')->where('caller_marital_status', '!=', '')
            ->select('caller_marital_status', DB::raw('count(*) as cnt'))
            ->groupBy('caller_marital_status')
            ->orderByDesc('cnt')
            ->get();

        // Monthly trends (last 12 months)
        $monthlyRaw = (clone $base)
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
                DB::raw('count(*) as cnt')
            )
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('cnt', 'ym');

        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months->put($key, $monthlyRaw->get($key, 0));
        }

        // Call validity
        $byValidity = (clone $base)
            ->whereNotNull('call_validity')
            ->select('call_validity', DB::raw('count(*) as cnt'))
            ->groupBy('call_validity')
            ->pluck('cnt', 'call_validity');

        // Age groups
        $ageGroups = [
            'Under 18'  => (clone $base)->whereBetween('caller_age', [1, 17])->count(),
            '18–24'     => (clone $base)->whereBetween('caller_age', [18, 24])->count(),
            '25–34'     => (clone $base)->whereBetween('caller_age', [25, 34])->count(),
            '35–44'     => (clone $base)->whereBetween('caller_age', [35, 44])->count(),
            '45+'       => (clone $base)->where('caller_age', '>=', 45)->count(),
        ];

        // Priority breakdown
        $byPriority = (clone $base)
            ->select('priority', DB::raw('count(*) as cnt'))
            ->groupBy('priority')
            ->pluck('cnt', 'priority');

        // Last updated
        $lastUpdated = (clone $base)->max('created_at');

        // ── Call stats by period (Day / Week / Month) ─────────────────────────
        $callPeriods = [
            'day'   => [now()->startOfDay(),   now()->endOfDay()],
            'week'  => [now()->startOfWeek(),  now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
        ];

        $callStats = [];
        foreach ($callPeriods as $key => [$start, $end]) {
            $cb = DB::table('calls')->whereBetween('started_at', [$start, $end]);
            $callStats[$key] = [
                'total'    => (clone $cb)->count(),
                'inbound'  => (clone $cb)->where('direction', 'inbound')->count(),
                'outbound' => (clone $cb)->where('direction', 'outbound')->count(),
                'missed'   => (clone $cb)->where('status', 'missed')->count(),
                'answered' => (clone $cb)->where('status', 'answered')->count(),
                'avg_dur'  => (int) (clone $cb)->where('status', 'answered')->avg('duration'),
            ];
        }

        // Trend: hourly for day, daily for week and month
        $callStats['day']['trend'] = collect(range(0, 23))->mapWithKeys(function ($h) {
            return [$h => 0];
        })->merge(
            DB::table('calls')
                ->whereBetween('started_at', [now()->startOfDay(), now()->endOfDay()])
                ->select(DB::raw('HOUR(started_at) as h'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('h')->orderBy('h')
                ->pluck('cnt', 'h')
        )->all();

        $callStats['week']['trend'] = DB::table('calls')
            ->whereBetween('started_at', [now()->startOfWeek(), now()->endOfDay()])
            ->select(DB::raw('DATE(started_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('d')->orderBy('d')
            ->pluck('cnt', 'd')->all();

        $callStats['month']['trend'] = DB::table('calls')
            ->whereBetween('started_at', [now()->startOfMonth(), now()->endOfDay()])
            ->select(DB::raw('DATE(started_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('d')->orderBy('d')
            ->pluck('cnt', 'd')->all();

        return view('public-dashboard', compact(
            'total', 'validTotal', 'repeatTotal', 'uptakeTotal', 'immediateAct',
            'byStatus', 'byProvince', 'byGender', 'byMode', 'byPurpose',
            'byService', 'byReferral', 'byKeyPops', 'byMarital', 'months',
            'byValidity', 'ageGroups', 'byPriority', 'lastUpdated',
            'callStats'
        ));
    }
}
