<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PublicDashboardController extends Controller
{
    public function index()
    {
        // ── All-time ticket base ──────────────────────────────────────────────
        $base = DB::table('tickets')->whereNull('deleted_at');

        $total        = (clone $base)->count();
        $validTotal   = (clone $base)->where('call_validity', 'valid')->count();
        $repeatTotal  = (clone $base)->where('is_repeat_caller', true)->count();
        $uptakeTotal  = (clone $base)->where('uptake_confirmed', true)->count();
        $immediateAct = (clone $base)->where('immediate_action_required', true)->count();

        $byStatus   = (clone $base)->select('status', DB::raw('count(*) as cnt'))->groupBy('status')->pluck('cnt', 'status');
        $byProvince = (clone $base)->whereNotNull('province')->where('province', '!=', '')->select('province', DB::raw('count(*) as cnt'))->groupBy('province')->orderByDesc('cnt')->get();
        $byGender   = (clone $base)->whereNotNull('caller_gender')->where('caller_gender', '!=', '')->select('caller_gender', DB::raw('count(*) as cnt'))->groupBy('caller_gender')->orderByDesc('cnt')->get();
        $byMode     = (clone $base)->whereNotNull('mode_of_communication')->where('mode_of_communication', '!=', '')->select('mode_of_communication', DB::raw('count(*) as cnt'))->groupBy('mode_of_communication')->orderByDesc('cnt')->get();
        $byPurpose  = (clone $base)->whereNotNull('purpose_of_call')->where('purpose_of_call', '!=', '')->select('purpose_of_call', DB::raw('count(*) as cnt'))->groupBy('purpose_of_call')->orderByDesc('cnt')->limit(10)->get();
        $byService  = (clone $base)->whereNotNull('services_requested')->where('services_requested', '!=', '')->select('services_requested', DB::raw('count(*) as cnt'))->groupBy('services_requested')->orderByDesc('cnt')->limit(10)->get();
        $byReferral = (clone $base)->whereNotNull('referred_to')->where('referred_to', '!=', '')->select('referred_to', DB::raw('count(*) as cnt'))->groupBy('referred_to')->orderByDesc('cnt')->limit(8)->get();
        $byKeyPops  = (clone $base)->whereNotNull('key_pops')->where('key_pops', '!=', '')->select('key_pops', DB::raw('count(*) as cnt'))->groupBy('key_pops')->orderByDesc('cnt')->limit(8)->get();
        $byMarital  = (clone $base)->whereNotNull('caller_marital_status')->where('caller_marital_status', '!=', '')->select('caller_marital_status', DB::raw('count(*) as cnt'))->groupBy('caller_marital_status')->orderByDesc('cnt')->get();
        $byValidity = (clone $base)->whereNotNull('call_validity')->select('call_validity', DB::raw('count(*) as cnt'))->groupBy('call_validity')->pluck('cnt', 'call_validity');
        $byPriority = (clone $base)->select('priority', DB::raw('count(*) as cnt'))->groupBy('priority')->pluck('cnt', 'priority');

        $ageGroups = [
            'Under 18' => (clone $base)->whereBetween('caller_age', [1, 17])->count(),
            '18–24'    => (clone $base)->whereBetween('caller_age', [18, 24])->count(),
            '25–34'    => (clone $base)->whereBetween('caller_age', [25, 34])->count(),
            '35–44'    => (clone $base)->whereBetween('caller_age', [35, 44])->count(),
            '45+'      => (clone $base)->where('caller_age', '>=', 45)->count(),
        ];

        $monthlyRaw = (clone $base)->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('count(*) as cnt'))
            ->groupBy('ym')->orderBy('ym')->pluck('cnt', 'ym');

        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months->put($key, $monthlyRaw->get($key, 0));
        }

        $lastUpdated = (clone $base)->max('created_at');

        // ── Period-filtered ticket data (Day / Week / Month) ─────────────────
        $ticketPeriods = [
            'day'   => [now()->startOfDay(),   now()->endOfDay()],
            'week'  => [now()->startOfWeek(),  now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
        ];

        $periodData = [];
        foreach ($ticketPeriods as $pKey => [$start, $end]) {
            $pb = DB::table('tickets')->whereNull('deleted_at')->whereBetween('created_at', [$start, $end]);
            $ptotal = (clone $pb)->count();

            // trend by hour (day) or by date (week/month)
            if ($pKey === 'day') {
                $hours = collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => 0]);
                $trend = $hours->merge(
                    (clone $pb)->select(DB::raw('HOUR(created_at) as h'), DB::raw('COUNT(*) as cnt'))
                        ->groupBy('h')->pluck('cnt', 'h')
                )->all();
            } else {
                $trend = (clone $pb)->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('d')->orderBy('d')->pluck('cnt', 'd')->all();
            }

            $periodData[$pKey] = [
                'total'    => $ptotal,
                'valid'    => (clone $pb)->where('call_validity', 'valid')->count(),
                'repeat'   => (clone $pb)->where('is_repeat_caller', true)->count(),
                'uptake'   => (clone $pb)->where('uptake_confirmed', true)->count(),
                'imm_act'  => (clone $pb)->where('immediate_action_required', true)->count(),
                'trend'    => $trend,

                'by_status'   => (clone $pb)->select('status', DB::raw('count(*) as cnt'))->groupBy('status')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->status, $r->cnt]),
                'by_priority' => (clone $pb)->select('priority', DB::raw('count(*) as cnt'))->groupBy('priority')->get()->map(fn ($r) => [$r->priority, $r->cnt]),
                'by_mode'     => (clone $pb)->whereNotNull('mode_of_communication')->where('mode_of_communication', '!=', '')->select('mode_of_communication', DB::raw('count(*) as cnt'))->groupBy('mode_of_communication')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->mode_of_communication, $r->cnt]),

                'by_province' => (clone $pb)->whereNotNull('province')->where('province', '!=', '')->select('province', DB::raw('count(*) as cnt'))->groupBy('province')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->province, $r->cnt]),

                'by_gender'   => (clone $pb)->whereNotNull('caller_gender')->where('caller_gender', '!=', '')->select('caller_gender', DB::raw('count(*) as cnt'))->groupBy('caller_gender')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->caller_gender, $r->cnt]),
                'by_marital'  => (clone $pb)->whereNotNull('caller_marital_status')->where('caller_marital_status', '!=', '')->select('caller_marital_status', DB::raw('count(*) as cnt'))->groupBy('caller_marital_status')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->caller_marital_status, $r->cnt]),
                'by_key_pops' => (clone $pb)->whereNotNull('key_pops')->where('key_pops', '!=', '')->select('key_pops', DB::raw('count(*) as cnt'))->groupBy('key_pops')->orderByDesc('cnt')->limit(8)->get()->map(fn ($r) => [$r->key_pops, $r->cnt]),
                'age_groups'  => [
                    ['Under 18', (clone $pb)->whereBetween('caller_age', [1,  17])->count()],
                    ['18–24',    (clone $pb)->whereBetween('caller_age', [18, 24])->count()],
                    ['25–34',    (clone $pb)->whereBetween('caller_age', [25, 34])->count()],
                    ['35–44',    (clone $pb)->whereBetween('caller_age', [35, 44])->count()],
                    ['45+',      (clone $pb)->where('caller_age', '>=', 45)->count()],
                ],

                'by_service'  => (clone $pb)->whereNotNull('services_requested')->where('services_requested', '!=', '')->select('services_requested', DB::raw('count(*) as cnt'))->groupBy('services_requested')->orderByDesc('cnt')->limit(10)->get()->map(fn ($r) => [$r->services_requested, $r->cnt]),
                'by_referral' => (clone $pb)->whereNotNull('referred_to')->where('referred_to', '!=', '')->select('referred_to', DB::raw('count(*) as cnt'))->groupBy('referred_to')->orderByDesc('cnt')->limit(8)->get()->map(fn ($r) => [$r->referred_to, $r->cnt]),
                'by_purpose'  => (clone $pb)->whereNotNull('purpose_of_call')->where('purpose_of_call', '!=', '')->select('purpose_of_call', DB::raw('count(*) as cnt'))->groupBy('purpose_of_call')->orderByDesc('cnt')->limit(10)->get()->map(fn ($r) => [$r->purpose_of_call, $r->cnt]),
            ];
        }

        // ── Period-filtered call data ─────────────────────────────────────────
        $callStats = [];
        foreach ($ticketPeriods as $pKey => [$start, $end]) {
            $cb = DB::table('calls')->whereBetween('started_at', [$start, $end]);
            $callStats[$pKey] = [
                'total'    => (clone $cb)->count(),
                'inbound'  => (clone $cb)->where('direction', 'inbound')->count(),
                'outbound' => (clone $cb)->where('direction', 'outbound')->count(),
                'missed'   => (clone $cb)->where('status', 'missed')->count(),
                'answered' => (clone $cb)->where('status', 'answered')->count(),
                'avg_dur'  => (int) (clone $cb)->where('status', 'answered')->avg('duration'),
            ];
        }

        $callStats['day']['trend'] = collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => 0])
            ->merge(DB::table('calls')->whereBetween('started_at', [now()->startOfDay(), now()->endOfDay()])
                ->select(DB::raw('HOUR(started_at) as h'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('h')->orderBy('h')->pluck('cnt', 'h'))->all();

        $callStats['week']['trend'] = DB::table('calls')->whereBetween('started_at', [now()->startOfWeek(), now()->endOfDay()])
            ->select(DB::raw('DATE(started_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('d')->orderBy('d')->pluck('cnt', 'd')->all();

        $callStats['month']['trend'] = DB::table('calls')->whereBetween('started_at', [now()->startOfMonth(), now()->endOfDay()])
            ->select(DB::raw('DATE(started_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('d')->orderBy('d')->pluck('cnt', 'd')->all();

        return view('public-dashboard', compact(
            'total', 'validTotal', 'repeatTotal', 'uptakeTotal', 'immediateAct',
            'byStatus', 'byProvince', 'byGender', 'byMode', 'byPurpose',
            'byService', 'byReferral', 'byKeyPops', 'byMarital', 'months',
            'byValidity', 'ageGroups', 'byPriority', 'lastUpdated',
            'callStats', 'periodData'
        ));
    }
}
