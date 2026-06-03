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
        $service = trim($request->input('service', ''));
        $d = $this->buildData($service);

        return response()
            ->view('public-dashboard', $d)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function data(Request $request): JsonResponse
    {
        $service = trim($request->input('service', ''));
        $d = $this->buildData($service);

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
            'serviceUptake'      => $d['serviceUptake'],
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

    private function buildData(string $serviceFilter = ''): array
    {
        // ── All-time scalars ─────────────────────────────────────────────────
        // Always build unfiltered list of services for the dropdown
        $allServices = DB::table('tickets')->whereNull('deleted_at')
            ->whereNotNull('services_requested')->where('services_requested', '!=', '')
            ->distinct()->orderBy('services_requested')->pluck('services_requested');

        $a = DB::table('tickets')->whereNull('deleted_at')
            ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter))
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
            ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter));

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

        $monthlyRaw = (clone $base)->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('count(*) as cnt'))
            ->groupBy('ym')->orderBy('ym')->pluck('cnt', 'ym');

        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months->put($key, $monthlyRaw->get($key, 0));
        }

        $lastUpdated = (clone $base)->max('created_at');

        // ── Period-filtered ticket data ──────────────────────────────────────
        $ticketPeriods = [
            'day'   => [now()->startOfDay(),   now()->endOfDay()],
            'week'  => [now()->startOfWeek(),  now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            'year'  => [now()->startOfYear(),  now()->endOfDay()],
        ];

        $periodData = [];
        foreach ($ticketPeriods as $pKey => [$start, $end]) {
            $pb = DB::table('tickets')->whereNull('deleted_at')->whereBetween('created_at', [$start, $end])
                ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter));

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
                'by_service'         => (clone $pb)->whereNotNull('services_requested')->where('services_requested', '!=', '')->select('services_requested', DB::raw('count(*) as cnt'))->groupBy('services_requested')->orderByDesc('cnt')->limit(12)->get()->map(fn ($r) => [$r->services_requested, $r->cnt]),
                'by_service_uptake'  => (clone $pb)->where('uptake_confirmed', 1)->whereNotNull('services_requested')->where('services_requested', '!=', '')->select('services_requested', DB::raw('count(*) as cnt'))->groupBy('services_requested')->pluck('cnt', 'services_requested'),
                'by_referral'        => (clone $pb)->whereNotNull('referred_to')->where('referred_to', '!=', '')->select('referred_to', DB::raw('count(*) as cnt'))->groupBy('referred_to')->orderByDesc('cnt')->limit(8)->get()->map(fn ($r) => [$r->referred_to, $r->cnt]),
                'by_purpose'         => (clone $pb)->whereNotNull('purpose_of_call')->where('purpose_of_call', '!=', '')->select('purpose_of_call', DB::raw('count(*) as cnt'))->groupBy('purpose_of_call')->orderByDesc('cnt')->limit(10)->get()->map(fn ($r) => [$r->purpose_of_call, $r->cnt]),
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
                ->when($serviceFilter, fn ($q) => $q->where('services_requested', $serviceFilter));
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

        // ── Smart defaults ───────────────────────────────────────────────────
        $ticketDefaultPeriod = 'month';
        foreach (['day', 'week', 'month', 'year'] as $p) {
            if (($periodData[$p]['total'] ?? 0) > 0) { $ticketDefaultPeriod = $p; break; }
        }
        $callDefaultPeriod = 'month';
        foreach (['day', 'week', 'month', 'year'] as $p) {
            if (($callStats[$p]['total'] ?? 0) > 0) { $callDefaultPeriod = $p; break; }
        }

        $uchat       = app(UchatService::class)->fetchAnalytics();
        $urgentOpen  = UrgentCase::where('status', 'open')->count();

        // ── New overview card metrics ─────────────────────────────────────────
        $resolvedTotal   = (int) ($byStatus->get('resolved') ?? 0);
        $invalidTotal    = (int) ($byValidity->get('invalid') ?? 0);
        $pendingTotal    = (int) (($byStatus->get('open') ?? 0) + ($byStatus->get('in_progress') ?? 0));
        $pendingPct      = $total > 0 ? round($pendingTotal / $total * 100, 1) : 0;
        $referredTotal   = (int) $byReferral->sum('cnt');
        $newCallers      = $total - $repeatTotal;
        $referralCompPct = $referredTotal > 0 ? round($uptakeTotal / $referredTotal * 100, 1) : 0;

        $genderSum    = max(1, $byGender->sum('cnt'));
        $maleTotal    = (int) ($byGender->firstWhere('caller_gender', 'male')?->cnt ?? 0);
        $femaleTotal  = (int) ($byGender->firstWhere('caller_gender', 'female')?->cnt ?? 0);
        $malePct      = round($maleTotal  / $genderSum * 100, 1);
        $femalePct    = round($femaleTotal / $genderSum * 100, 1);

        // Service vs uptake (Referral By Service chart)
        $serviceUptake = DB::table('tickets')->whereNull('deleted_at')
            ->whereNotNull('services_requested')->where('services_requested', '!=', '')
            ->select('services_requested', DB::raw('COUNT(*) as total'), DB::raw('SUM(uptake_confirmed=1) as uptake'))
            ->groupBy('services_requested')->orderByDesc('total')->limit(8)->get();

        // SBC / YALeP count from signups table (if exists)
        $sbcTotal = 0;
        try { $sbcTotal = DB::table('sbc_signups')->count(); } catch (\Exception) {}

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
            'serviceUptake', 'sbcTotal',
            'serviceFilter', 'allServices'
        );
    }
}
