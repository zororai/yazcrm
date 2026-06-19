<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\CallTargetController;
use App\Models\UchatAnalyticsSnapshot;
use App\Models\UrgentCase;
use App\Services\UchatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicDashboardController extends Controller
{
    public function index(Request $request)
    {
        $service      = trim($request->input('service', ''));
        $project      = trim($request->input('project', ''));
        $year         = $request->input('year');
        $month        = $request->input('month');
        $dateFrom     = trim($request->input('date_from', ''));
        $dateTo       = trim($request->input('date_to', ''));
        $genderFilter = trim($request->input('gender', ''));
        $ageFilter    = trim($request->input('age', ''));
        $d = $this->buildData($service, $year, $month, $project, $dateFrom, $dateTo, $genderFilter, $ageFilter);

        return response()
            ->view('public-dashboard', $d)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function data(Request $request): JsonResponse
    {
        $service      = trim($request->input('service', ''));
        $project      = trim($request->input('project', ''));
        $year         = $request->input('year');
        $month        = $request->input('month');
        $dateFrom     = trim($request->input('date_from', ''));
        $dateTo       = trim($request->input('date_to', ''));
        $genderFilter = trim($request->input('gender', ''));
        $ageFilter    = trim($request->input('age', ''));
        $d = $this->buildData($service, $year, $month, $project, $dateFrom, $dateTo, $genderFilter, $ageFilter);

        return response()->json([
            'periodData'          => $d['periodData'],
            'callStats'           => $d['callStats'],
            'months'              => $d['months'],
            'prevPeriodData'      => $d['prevPeriodData'],
            'callTargetRows'      => $d['callTargetRows'],
            'ticketDefaultPeriod' => $d['ticketDefaultPeriod'],
            'callDefaultPeriod'   => $d['callDefaultPeriod'],
            'lastUpdated'         => $d['lastUpdated'],
            'total'               => $d['total'],
            'uchat'              => $d['uchat'],
            'urgentOpen'         => $d['urgentOpen'],
            'resolvedTotal'      => $d['resolvedTotal'],
            'invalidTotal'       => $d['invalidTotal'],
            'pendingPct'         => $d['pendingPct'],
            'referredTotal'      => $d['referredTotal'],
            'newCallers'         => $d['newCallers'],
            'referralCompPct'    => $d['referralCompPct'],
            'malePct'            => $d['malePct'],
            'femalePct'          => $d['femalePct'],
            'serviceFilter'      => $d['serviceFilter'],
            'allServices'        => $d['allServices'],
            'projectFilter'      => $d['projectFilter'],
            'allProjects'        => $d['allProjects'],
            'dateFrom'           => $d['dateFrom'],
            'dateTo'             => $d['dateTo'],
            'genderFilter'       => $d['genderFilter'],
            'ageFilter'          => $d['ageFilter'],
            'serviceUptake'      => $d['serviceUptake'],
            'availableYears'     => $d['availableYears'],
            'byPurpose'          => $d['byPurpose']->map(fn($r) => ['purpose_of_call' => $r->purpose_of_call, 'cnt' => $r->cnt]),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    public function uchatHistory(): JsonResponse
    {
        $rows = UchatAnalyticsSnapshot::orderBy('date')
            ->get(['date', 'total_bot_users', 'new_bot_users', 'active_today']);

        return response()->json($rows)->withHeaders([
            'Cache-Control' => 'no-store',
        ]);
    }

    private function buildData(string $serviceFilter = '', ?string $selectedYear = null, ?string $selectedMonth = null, string $projectFilter = '', string $dateFrom = '', string $dateTo = '', string $genderFilter = '', string $ageFilter = ''): array
    {
        // ── All-time scalars ─────────────────────────────────────────────────
        // Always build unfiltered list of services for the dropdown
        $allServices = DB::table('tickets')->whereNull('deleted_at')
            ->whereNotNull('services_requested')->where('services_requested', '!=', '')
            ->distinct()->orderBy('services_requested')->pluck('services_requested');

        // Always build unfiltered list of projects for the dropdown
        $allProjects = DB::table('tickets')->whereNull('deleted_at')
            ->whereNotNull('project')->where('project', '!=', '')
            ->distinct()->orderBy('project')->pluck('project');

        // Age filter closure — defined early so all queries can use it
        $applyAge = function ($q) use ($ageFilter) {
            return match($ageFilter) {
                'u18'   => $q->whereBetween('caller_age', [1, 17]),
                '18-24' => $q->whereBetween('caller_age', [18, 24]),
                '25-34' => $q->whereBetween('caller_age', [25, 34]),
                '35-44' => $q->whereBetween('caller_age', [35, 44]),
                '45p'   => $q->where('caller_age', '>=', 45),
                default => $q,
            };
        };

        $a = DB::table('tickets')->whereNull('deleted_at')
            ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter))
            ->when($projectFilter, fn ($q) => $q->where('project', $projectFilter))
            ->when($genderFilter,  fn ($q) => $q->where('caller_gender', $genderFilter))
            ->when($ageFilter,     $applyAge)
            ->selectRaw('
            COUNT(*) as total,
            SUM(call_validity = "valid")          as valid_total,
            SUM(is_repeat_caller = 1)             as repeat_total,
            SUM(uptake_confirmed = 1)             as uptake_total,
            SUM(immediate_action_required = 1)    as immediate_act,
            SUM(caller_age BETWEEN 1 AND 17)      as age_u18,
            SUM(caller_age BETWEEN 18 AND 24)     as age_1824,
            SUM(caller_age BETWEEN 25 AND 34)     as age_2534,
            SUM(caller_age BETWEEN 35 AND 44)     as age_3544,
            SUM(caller_age >= 45)                 as age_45p
        ')->first();

        $total        = (int) $a->total;
        $validTotal   = (int) $a->valid_total;
        $repeatTotal  = (int) $a->repeat_total;
        $uptakeTotal  = (int) $a->uptake_total;
        $immediateAct = (int) $a->immediate_act;
        $ageGroups    = [
            'Under 18' => (int) $a->age_u18,
            '18–24'    => (int) $a->age_1824,
            '25–34'    => (int) $a->age_2534,
            '35–44'    => (int) $a->age_3544,
            '45+'      => (int) $a->age_45p,
        ];

        // ── All-time groupby queries ─────────────────────────────────────────
        $base = DB::table('tickets')->whereNull('deleted_at')
            ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter))
            ->when($projectFilter, fn ($q) => $q->where('project', $projectFilter))
            ->when($genderFilter,  fn ($q) => $q->where('caller_gender', $genderFilter))
            ->when($ageFilter,     $applyAge);

        $byStatus   = (clone $base)->select('status', DB::raw('count(*) as cnt'))->groupBy('status')->pluck('cnt', 'status');
        $byProvince = (clone $base)->whereNotNull('province')->where('province', '!=', '')->select('province', DB::raw('count(*) as cnt'))->groupBy('province')->orderByDesc('cnt')->get();
        $byGender   = (clone $base)->whereNotNull('caller_gender')->where('caller_gender', '!=', '')->select('caller_gender', DB::raw('count(*) as cnt'))->groupBy('caller_gender')->orderByDesc('cnt')->get();
        $byMode     = (clone $base)->whereNotNull('mode_of_communication')->where('mode_of_communication', '!=', '')->select('mode_of_communication', DB::raw('count(*) as cnt'))->groupBy('mode_of_communication')->orderByDesc('cnt')->get();
        $byPurpose  = (clone $base)->whereNotNull('purpose_of_call')->where('purpose_of_call', '!=', '')->select('purpose_of_call', DB::raw('count(*) as cnt'))->groupBy('purpose_of_call')->orderByDesc('cnt')->limit(10)->get();
        $byService  = (clone $base)->whereNotNull('services_requested')->where('services_requested', '!=', '')->select('services_requested', DB::raw('count(*) as cnt'))->groupBy('services_requested')->orderByDesc('cnt')->limit(10)->get();
        $byReferral = (clone $base)->whereNotNull('referred_to')->where('referred_to', '!=', '')->whereRaw("UPPER(TRIM(referred_to)) NOT REGEXP '^N[^A-Z]*A?$'")->select('referred_to', DB::raw('count(*) as cnt'))->groupBy('referred_to')->orderByDesc('cnt')->limit(8)->get();
        $byKeyPops  = (clone $base)->whereNotNull('key_pops')->where('key_pops', '!=', '')->select('key_pops', DB::raw('count(*) as cnt'))->groupBy('key_pops')->orderByDesc('cnt')->limit(8)->get();
        $byMarital  = (clone $base)->whereNotNull('caller_marital_status')->where('caller_marital_status', '!=', '')->select('caller_marital_status', DB::raw('count(*) as cnt'))->groupBy('caller_marital_status')->orderByDesc('cnt')->get();
        $byValidity = (clone $base)->whereNotNull('call_validity')->select('call_validity', DB::raw('count(*) as cnt'))->groupBy('call_validity')->pluck('cnt', 'call_validity');
        $byPriority = (clone $base)->select('priority', DB::raw('count(*) as cnt'))->groupBy('priority')->pluck('cnt', 'priority');

        $monthlyRaw = (clone $base)->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('count(*) as cnt'))
            ->groupBy('ym')->orderBy('ym')->pluck('cnt', 'ym');

        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months->put($key, $monthlyRaw->get($key, 0));
        }

        $lastUpdated = (clone $base)->max('created_at');

        // ── All-time Age × Gender breakdown for overview chart ──────────────
        $ageGenderData = (clone $base)
            ->whereNotNull('caller_gender')->where('caller_gender', '!=', '')
            ->whereBetween('caller_age', [10, 120])
            ->selectRaw("
                CASE
                    WHEN caller_age BETWEEN 10 AND 14 THEN '10-14'
                    WHEN caller_age BETWEEN 15 AND 19 THEN '15-19'
                    WHEN caller_age BETWEEN 20 AND 25 THEN '20-25'
                    ELSE '25+'
                END as age_band,
                caller_gender,
                COUNT(*) as cnt
            ")
            ->groupBy('age_band', 'caller_gender')
            ->get()
            ->groupBy('age_band')
            ->map(fn ($rows) => $rows->pluck('cnt', 'caller_gender')->toArray())
            ->toArray();

        // ── Available years (for year picker) ───────────────────────────────
        $availableYears = DB::table('tickets')->whereNull('deleted_at')
            ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter))
            ->when($projectFilter, fn ($q) => $q->where('project', $projectFilter))
            ->when($genderFilter,  fn ($q) => $q->where('caller_gender', $genderFilter))
            ->when($ageFilter,     $applyAge)
            ->selectRaw("YEAR(created_at) as yr")
            ->groupBy('yr')->orderByDesc('yr')->pluck('yr')->map(fn ($y) => (int) $y)->values();

        // ── Period-filtered ticket data ──────────────────────────────────────
        // Year period: use selected year or current year
        $yearVal = $selectedYear && preg_match('/^\d{4}$/', $selectedYear)
            ? (int) $selectedYear : (int) now()->format('Y');
        $yearStart = \Carbon\Carbon::create($yearVal, 1, 1)->startOfDay();
        $yearEnd   = \Carbon\Carbon::create($yearVal, 12, 31)->endOfDay();

        // Month period: use selected month (YYYY-MM) or current month
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfDay();
        if ($selectedMonth && preg_match('/^(\d{4})-(\d{2})$/', $selectedMonth, $m)) {
            $monthStart = \Carbon\Carbon::create((int)$m[1], (int)$m[2], 1)->startOfDay();
            $monthEnd   = $monthStart->copy()->endOfMonth()->endOfDay();
        }

        // Date range period (From–To months)
        $rangeStart = null;
        $rangeEnd   = null;
        if ($dateFrom && preg_match('/^(\d{4})-(\d{2})$/', $dateFrom, $dfm)) {
            $rangeStart = \Carbon\Carbon::create((int)$dfm[1], (int)$dfm[2], 1)->startOfDay();
        }
        if ($dateTo && preg_match('/^(\d{4})-(\d{2})$/', $dateTo, $dtm)) {
            $rangeEnd = \Carbon\Carbon::create((int)$dtm[1], (int)$dtm[2], 1)->endOfMonth()->endOfDay();
        } elseif ($rangeStart) {
            $rangeEnd = $rangeStart->copy()->endOfMonth()->endOfDay();
        }

        $ticketPeriods = [
            'day'   => [now()->startOfDay(),  now()->endOfDay()],
            'week'  => [now()->startOfWeek(), now()->endOfDay()],
            'month' => [$monthStart, $monthEnd],
            'year'  => [$yearStart,  $yearEnd],
        ];
        if ($rangeStart && $rangeEnd) {
            $ticketPeriods['range'] = [$rangeStart, $rangeEnd];
        }

        $periodData = [];
        foreach ($ticketPeriods as $pKey => [$start, $end]) {
            $pb = DB::table('tickets')->whereNull('deleted_at')->whereBetween('created_at', [$start, $end])
                ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter))
                ->when($projectFilter, fn ($q) => $q->where('project', $projectFilter))
                ->when($genderFilter,  fn ($q) => $q->where('caller_gender', $genderFilter))
                ->when($ageFilter,     $applyAge);

            $ps = (clone $pb)->selectRaw('
                COUNT(*) as total,
                SUM(call_validity = "valid")       as valid,
                SUM(is_repeat_caller = 1)          as `repeat`,
                SUM(uptake_confirmed = 1)          as uptake,
                SUM(immediate_action_required = 1) as imm_act,
                SUM(caller_age BETWEEN 1 AND 17)   as age_u18,
                SUM(caller_age BETWEEN 18 AND 24)  as age_1824,
                SUM(caller_age BETWEEN 25 AND 34)  as age_2534,
                SUM(caller_age BETWEEN 35 AND 44)  as age_3544,
                SUM(caller_age >= 45)              as age_45p
            ')->first();

            if ($pKey === 'day') {
                $trend = array_replace(
                    array_fill(0, 24, 0),
                    (clone $pb)->select(DB::raw('HOUR(created_at) as h'), DB::raw('COUNT(*) as cnt'))
                        ->groupBy('h')->pluck('cnt', 'h')->toArray()
                );
            } elseif ($pKey === 'year') {
                $allMonths = collect(range(1, 12))->mapWithKeys(fn ($m) => [
                    now()->startOfYear()->copy()->month($m)->format('Y-m') => 0,
                ]);
                $trend = $allMonths->merge(
                    (clone $pb)->select(DB::raw("DATE_FORMAT(created_at,'%Y-%m') as ym"), DB::raw('COUNT(*) as cnt'))
                        ->groupBy('ym')->orderBy('ym')->pluck('cnt', 'ym')
                )->all();
            } elseif ($pKey === 'range') {
                $trend = (clone $pb)->select(DB::raw("DATE_FORMAT(created_at,'%Y-%m') as ym"), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('ym')->orderBy('ym')->pluck('cnt', 'ym')->all();
            } else {
                $trend = (clone $pb)->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('d')->orderBy('d')->pluck('cnt', 'd')->all();
            }

            $periodData[$pKey] = [
                'total'   => (int) $ps->total,
                'valid'   => (int) $ps->valid,
                'repeat'  => (int) $ps->repeat,
                'uptake'  => (int) $ps->uptake,
                'imm_act' => (int) $ps->imm_act,
                'trend'   => $trend,
                'age_groups' => [
                    ['Under 18', (int) $ps->age_u18],
                    ['18–24',    (int) $ps->age_1824],
                    ['25–34',    (int) $ps->age_2534],
                    ['35–44',    (int) $ps->age_3544],
                    ['45+',      (int) $ps->age_45p],
                ],
                'by_status'          => (clone $pb)->select('status', DB::raw('count(*) as cnt'))->groupBy('status')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->status, $r->cnt]),
                'by_priority'        => (clone $pb)->select('priority', DB::raw('count(*) as cnt'))->groupBy('priority')->get()->map(fn ($r) => [$r->priority, $r->cnt]),
                'by_mode'            => (clone $pb)->whereNotNull('mode_of_communication')->where('mode_of_communication', '!=', '')->select('mode_of_communication', DB::raw('count(*) as cnt'))->groupBy('mode_of_communication')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->mode_of_communication, $r->cnt]),
                'by_province'        => (clone $pb)->whereNotNull('province')->where('province', '!=', '')->select('province', DB::raw('count(*) as cnt'))->groupBy('province')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->province, $r->cnt]),
                'by_gender'          => (clone $pb)->whereNotNull('caller_gender')->where('caller_gender', '!=', '')->select('caller_gender', DB::raw('count(*) as cnt'))->groupBy('caller_gender')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->caller_gender, $r->cnt]),
                'by_marital'         => (clone $pb)->whereNotNull('caller_marital_status')->where('caller_marital_status', '!=', '')->select('caller_marital_status', DB::raw('count(*) as cnt'))->groupBy('caller_marital_status')->orderByDesc('cnt')->get()->map(fn ($r) => [$r->caller_marital_status, $r->cnt]),
                'by_key_pops'        => (clone $pb)->whereNotNull('key_pops')->where('key_pops', '!=', '')->select('key_pops', DB::raw('count(*) as cnt'))->groupBy('key_pops')->orderByDesc('cnt')->limit(8)->get()->map(fn ($r) => [$r->key_pops, $r->cnt]),
                'referral_count'     => (clone $pb)->whereNotNull('referred_to')->where('referred_to', '!=', '')->count(),
                'service_count'      => (clone $pb)->whereNotNull('services_requested')->where('services_requested', '!=', '')->count(),
                'by_service'         => DB::table('tickets as t2')
                    ->whereNull('t2.deleted_at')
                    ->whereBetween('t2.created_at', [$start, $end])
                    ->when($serviceFilter, fn ($q) => $q->where('t2.services_requested', $serviceFilter))
                    ->when($projectFilter, fn ($q) => $q->where('t2.project', $projectFilter))
                    ->whereNotNull('t2.services_requested')->where('t2.services_requested', '!=', '')
                    ->leftJoin('lookup_items as li', fn ($j) => $j->on('li.name', '=', 't2.services_requested')->where('li.type', 'service_requested'))
                    ->select('t2.services_requested', DB::raw('count(*) as cnt'), 'li.classification_categories')
                    ->groupBy('t2.services_requested', 'li.classification_categories')
                    ->orderByDesc('cnt')->limit(12)->get()
                    ->map(fn ($r) => [
                        $r->services_requested,
                        $r->cnt,
                        json_decode($r->classification_categories ?? '[]', true) ?? [],
                    ]),
                'by_service_uptake'  => (clone $pb)->where('uptake_confirmed', 1)->whereNotNull('services_requested')->where('services_requested', '!=', '')->select('services_requested', DB::raw('count(*) as cnt'))->groupBy('services_requested')->pluck('cnt', 'services_requested'),
                'psychosocial_breakdown' => (clone $pb)
                    ->where('services_requested', 'Psycho-social support')
                    ->whereNotNull('psychosocial_type')
                    ->where('psychosocial_type', '!=', '')
                    ->select('psychosocial_type', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(uptake_confirmed=1) as uptake'))
                    ->groupBy('psychosocial_type')
                    ->orderByDesc('cnt')
                    ->get()
                    ->map(fn ($r) => ['type' => $r->psychosocial_type, 'total' => (int)$r->cnt, 'uptake' => (int)$r->uptake]),
                'by_referral'        => (clone $pb)->whereNotNull('referred_to')->where('referred_to', '!=', '')->whereRaw("UPPER(TRIM(referred_to)) NOT REGEXP '^N[^A-Z]*A?$'")->select('referred_to', DB::raw('count(*) as cnt'))->groupBy('referred_to')->orderByDesc('cnt')->limit(8)->get()->map(fn ($r) => [$r->referred_to, $r->cnt]),
                'by_purpose'         => (clone $pb)->whereNotNull('purpose_of_call')->where('purpose_of_call', '!=', '')->select('purpose_of_call', DB::raw('count(*) as cnt'))->groupBy('purpose_of_call')->orderByDesc('cnt')->limit(10)->get()->map(fn ($r) => [$r->purpose_of_call, $r->cnt]),
                'by_age_gender'      => (clone $pb)
                    ->whereNotNull('caller_gender')->where('caller_gender', '!=', '')
                    ->whereBetween('caller_age', [10, 120])
                    ->selectRaw("
                        CASE
                            WHEN caller_age BETWEEN 10 AND 14 THEN '10-14'
                            WHEN caller_age BETWEEN 15 AND 19 THEN '15-19'
                            WHEN caller_age BETWEEN 20 AND 25 THEN '20-25'
                            ELSE '25+'
                        END as age_band,
                        caller_gender,
                        COUNT(*) as cnt
                    ")
                    ->groupBy('age_band', 'caller_gender')
                    ->get()
                    ->groupBy('age_band')
                    ->map(fn ($rows) => $rows->pluck('cnt', 'caller_gender')->toArray())
                    ->toArray(),
                'by_agent'           => DB::table('users')
                    ->where('users.is_active', true)
                    ->leftJoin('extensions', 'extensions.user_id', '=', 'users.id')
                    ->leftJoin('tickets', function ($join) use ($start, $end, $serviceFilter, $projectFilter, $genderFilter, $ageFilter) {
                        $join->on('tickets.agent_id', '=', 'users.id')
                             ->whereNull('tickets.deleted_at')
                             ->whereBetween('tickets.created_at', [$start, $end]);
                        if ($serviceFilter) $join->where('tickets.services_requested', $serviceFilter);
                        if ($projectFilter) $join->where('tickets.project', $projectFilter);
                        if ($genderFilter)  $join->where('tickets.caller_gender', $genderFilter);
                        if ($ageFilter) {
                            match($ageFilter) {
                                'u18'   => $join->whereBetween('tickets.caller_age', [1, 17]),
                                '18-24' => $join->whereBetween('tickets.caller_age', [18, 24]),
                                '25-34' => $join->whereBetween('tickets.caller_age', [25, 34]),
                                '35-44' => $join->whereBetween('tickets.caller_age', [35, 44]),
                                '45p'   => $join->where('tickets.caller_age', '>=', 45),
                                default => null,
                            };
                        }
                    })
                    ->selectRaw('
                        users.id as user_id,
                        users.name as agent_name,
                        extensions.extension_number,
                        COUNT(tickets.id) as total,
                        SUM(tickets.status = "open") as open_count,
                        SUM(tickets.status IN ("in_progress","ongoing")) as in_progress_count,
                        SUM(tickets.status IN ("resolved","closed")) as resolved_count
                    ')
                    ->groupBy('users.id', 'users.name', 'extensions.extension_number')
                    ->orderByDesc('total')
                    ->orderBy('users.name')
                    ->get()
                    ->map(fn ($r) => [
                        'agent'      => $r->agent_name,
                        'extension'  => $r->extension_number,
                        'total'      => (int) $r->total,
                        'open'       => (int) $r->open_count,
                        'in_progress'=> (int) $r->in_progress_count,
                        'resolved'   => (int) $r->resolved_count,
                    ]),
            ];
        }

        // ── Previous periods ─────────────────────────────────────────────────
        $prevBounds = [
            'day'   => [now()->subDay()->startOfDay(),     now()->subDay()->endOfDay()],
            'week'  => [now()->subWeek()->startOfWeek(),   now()->subWeek()->endOfWeek()],
            'month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'year'  => [now()->subYear()->startOfYear(),   now()->subYear()->endOfYear()],
        ];
        $prevPeriodData = [];
        foreach ($prevBounds as $pKey => [$start, $end]) {
            $pb = DB::table('tickets')->whereNull('deleted_at')->whereBetween('created_at', [$start, $end])
                ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter))
                ->when($projectFilter, fn ($q) => $q->where('project', $projectFilter))
                ->when($genderFilter,  fn ($q) => $q->where('caller_gender', $genderFilter))
                ->when($ageFilter,     $applyAge);
            $prevPeriodData[$pKey] = [
                'total'      => (clone $pb)->count(),
                'by_purpose' => (clone $pb)->whereNotNull('purpose_of_call')->where('purpose_of_call', '!=', '')
                    ->select('purpose_of_call', DB::raw('count(*) as cnt'))
                    ->groupBy('purpose_of_call')->orderByDesc('cnt')->limit(10)
                    ->pluck('cnt', 'purpose_of_call'),
            ];
        }

        // ── Call target rows ─────────────────────────────────────────────────
        $callTargetRows = CallTargetController::allRows();

        // ── Recent tickets ───────────────────────────────────────────────────
        $recentTickets = DB::table('tickets')
            ->whereNull('deleted_at')
            ->select('id', 'created_at', 'mode_of_communication', 'purpose_of_call',
                     'priority', 'immediate_action_required', 'referred_to', 'status', 'province')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // ── Call stats ───────────────────────────────────────────────────────
        $callStats = [];
        foreach ($ticketPeriods as $pKey => [$start, $end]) {
            $cs = DB::table('calls')->whereBetween('started_at', [$start, $end])->selectRaw('
                COUNT(*) as total,
                SUM(direction = "inbound")  as inbound,
                SUM(direction = "outbound") as outbound,
                SUM(status = "missed")      as missed,
                SUM(status = "answered")    as answered,
                AVG(CASE WHEN status = "answered" THEN duration END) as avg_dur
            ')->first();
            $callStats[$pKey] = [
                'total'    => (int) $cs->total,
                'inbound'  => (int) $cs->inbound,
                'outbound' => (int) $cs->outbound,
                'missed'   => (int) $cs->missed,
                'answered' => (int) $cs->answered,
                'avg_dur'  => (int) $cs->avg_dur,
            ];
        }

        $callStats['day']['trend'] = array_replace(
            array_fill(0, 24, 0),
            DB::table('calls')->whereBetween('started_at', [now()->startOfDay(), now()->endOfDay()])
                ->select(DB::raw('HOUR(started_at) as h'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('h')->orderBy('h')->pluck('cnt', 'h')->toArray()
        );
        $callStats['week']['trend'] = DB::table('calls')->whereBetween('started_at', [now()->startOfWeek(), now()->endOfDay()])
            ->select(DB::raw('DATE(started_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('d')->orderBy('d')->pluck('cnt', 'd')->all();
        $callStats['month']['trend'] = DB::table('calls')->whereBetween('started_at', [now()->startOfMonth(), now()->endOfDay()])
            ->select(DB::raw('DATE(started_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('d')->orderBy('d')->pluck('cnt', 'd')->all();
        $yearCallMonths = collect(range(1, 12))->mapWithKeys(fn ($m) => [
            now()->startOfYear()->copy()->month($m)->format('Y-m') => 0,
        ]);
        $callStats['year']['trend'] = $yearCallMonths->merge(
            DB::table('calls')->whereBetween('started_at', [now()->startOfYear(), now()->endOfDay()])
                ->select(DB::raw("DATE_FORMAT(started_at,'%Y-%m') as ym"), DB::raw('COUNT(*) as cnt'))
                ->groupBy('ym')->orderBy('ym')->pluck('cnt', 'ym')
        )->all();
        if ($rangeStart && $rangeEnd) {
            $callStats['range']['trend'] = DB::table('calls')->whereBetween('started_at', [$rangeStart, $rangeEnd])
                ->select(DB::raw("DATE_FORMAT(started_at,'%Y-%m') as ym"), DB::raw('COUNT(*) as cnt'))
                ->groupBy('ym')->orderBy('ym')->pluck('cnt', 'ym')->all();
        }

        // ── Smart defaults ───────────────────────────────────────────────────
        // If a date range is active, default all sections to 'range'
        if ($rangeStart && $rangeEnd) {
            $ticketDefaultPeriod = 'range';
            $callDefaultPeriod   = 'range';
        } else {
            $ticketDefaultPeriod = 'month';
            foreach (['day', 'week', 'month', 'year'] as $p) {
                if (($periodData[$p]['total'] ?? 0) > 0) { $ticketDefaultPeriod = $p; break; }
            }
            $callDefaultPeriod = 'month';
            foreach (['day', 'week', 'month', 'year'] as $p) {
                if (($callStats[$p]['total'] ?? 0) > 0) { $callDefaultPeriod = $p; break; }
            }
        }

        $uchat       = app(UchatService::class)->fetchAnalytics();
        $urgentOpen  = UrgentCase::where('status', 'open')->count();

        // ── New overview card metrics ─────────────────────────────────────────
        $resolvedTotal   = (int) ($byStatus->get('resolved') ?? 0);
        $invalidTotal    = (int) ($byValidity->get('invalid') ?? 0);
        $pendingTotal    = (int) (($byStatus->get('open') ?? 0) + ($byStatus->get('in_progress') ?? 0));
        $pendingPct      = $total > 0 ? round($pendingTotal / $total * 100, 1) : 0;
        // Count all tickets with a referred_to value (not capped to top 8 groups)
        $referredTotal   = (int) (clone $base)->whereNotNull('referred_to')->where('referred_to', '!=', '')->count();
        $newCallers      = $total - $repeatTotal;
        $referralCompPct = $referredTotal > 0 ? min(100, round($uptakeTotal / $referredTotal * 100, 1)) : 0;

        $genderSum    = max(1, $byGender->sum('cnt'));
        $maleTotal    = (int) ($byGender->firstWhere('caller_gender', 'male')?->cnt ?? 0);
        $femaleTotal  = (int) ($byGender->firstWhere('caller_gender', 'female')?->cnt ?? 0);
        $malePct      = round($maleTotal  / $genderSum * 100, 1);
        $femalePct    = round($femaleTotal / $genderSum * 100, 1);

        // Service vs uptake (Referral By Service chart) — joined with lookup for classification categories
        $serviceUptake = DB::table('tickets')->whereNull('deleted_at')
            ->whereNotNull('tickets.services_requested')->where('tickets.services_requested', '!=', '')
            ->leftJoin('lookup_items', function ($join) {
                $join->on('lookup_items.name', '=', 'tickets.services_requested')
                     ->where('lookup_items.type', '=', 'service_requested');
            })
            ->select(
                'tickets.services_requested',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(uptake_confirmed=1) as uptake'),
                'lookup_items.classification_categories'
            )
            ->groupBy('tickets.services_requested', 'lookup_items.classification_categories')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(function ($r) {
                $cats = json_decode($r->classification_categories ?? '[]', true) ?? [];
                return [
                    'services_requested'          => $r->services_requested,
                    'total'                       => (int) $r->total,
                    'uptake'                      => (int) $r->uptake,
                    'classification_categories'   => $cats,
                ];
            });

        // Display offset for specific projects
        $displayTotalOffset  = $projectFilter === 'UNICEF' ? 5000 : 0;
        $displayUptakeOffset = $projectFilter === 'UNICEF' ? 5686 : ($projectFilter ? 0 : 19821);

        // SBC / YALeP counts from signups table (if exists)
        $sbcTotal        = 0; // people who took the YALeP course (Certificates To Process)
        $sbcEngagedTotal = 0; // total youth engaged with chatbot (SBC Signups)
        try {
            $sbcTotal        = DB::table('sbc_signups')->where('sheet', 'Certificates To Process')->count() + 2002;
            $sbcEngagedTotal = DB::table('sbc_signups')->where('sheet', 'SBC Signups')->count() + 10922;
        } catch (\Exception) {}

        return compact(
            'total', 'validTotal', 'repeatTotal', 'uptakeTotal', 'immediateAct',
            'byStatus', 'byProvince', 'byGender', 'byMode', 'byPurpose',
            'byService', 'byReferral', 'byKeyPops', 'byMarital', 'months',
            'byValidity', 'ageGroups', 'byPriority', 'lastUpdated',
            'callStats', 'periodData',
            'ticketDefaultPeriod', 'callDefaultPeriod',
            'prevPeriodData', 'recentTickets', 'callTargetRows',
            'uchat', 'urgentOpen',
            'resolvedTotal', 'invalidTotal', 'pendingTotal', 'pendingPct',
            'referredTotal', 'newCallers', 'referralCompPct',
            'maleTotal', 'femaleTotal', 'malePct', 'femalePct',
            'serviceUptake', 'sbcTotal', 'sbcEngagedTotal', 'displayTotalOffset', 'displayUptakeOffset',
            'ageGenderData',
            'serviceFilter', 'allServices',
            'projectFilter', 'allProjects',
            'dateFrom', 'dateTo',
            'genderFilter', 'ageFilter',
            'availableYears', 'yearVal', 'selectedMonth', 'monthStart'
        );
    }
}
