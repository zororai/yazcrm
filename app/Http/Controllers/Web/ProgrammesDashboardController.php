<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Locations\Zimbabwe;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Programmes dashboard — the /screen helpline breakdowns (Total Cases, Calls
// by Case Type, Referral By Service, Demographics) for one programme (the
// ticket's `project`) over a chosen period, behind login, with PDF export.
// Definitions match PublicDashboardController so the numbers agree with /screen.
class ProgrammesDashboardController extends Controller
{
    private const PERIODS = [
        'month' => 'This month',
        '3m'    => 'Last 3 months',
        'year'  => 'This year',
        '12m'   => 'Last 12 months',
        'all'   => 'All time',
        'custom'=> 'Custom range',
    ];

    // Filter options mirror /screen (public-dashboard.blade.php).
    private const AGE_GROUPS = ['u18' => 'Under 18', '18-24' => '18 – 24', '25-34' => '25 – 34', '35-44' => '35 – 44', '45p' => '45+'];

    private const GENDERS = ['male' => 'Male', 'female' => 'Female'];

    private const PROVINCE_DISTRICTS = Zimbabwe::PROVINCE_DISTRICTS;

    private function authorizeView(User $user): void
    {
        abort_unless(
            $user->role === 'admin' || in_array('programmes_dashboard', $user->nav_permissions ?? [], true),
            403
        );
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request->user());

        $distinct = fn (string $col) => DB::table('tickets')->whereNull('deleted_at')
            ->whereNotNull($col)->where($col, '!=', '')->distinct()->orderBy($col)->pluck($col);

        return Inertia::render('Programmes/Dashboard', [
            ...$this->data($request),
            'programmes' => $this->programmes(),
            'periods'    => collect(self::PERIODS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'options'    => [
                'services'          => $distinct('services_requested'),
                'genders'           => self::GENDERS,
                'ageGroups'         => self::AGE_GROUPS,
                'provinceDistricts' => self::PROVINCE_DISTRICTS,
                'allDistricts'      => $distinct('district'),
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeView($request->user());

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.programmes-dashboard-pdf', [
            ...$this->data($request),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $slug = $request->filled('programme') ? '-' . str($request->input('programme'))->slug() : '';

        return $pdf->download("programmes-dashboard{$slug}-" . now()->format('Y-m-d') . '.pdf');
    }

    private function programmes()
    {
        return DB::table('tickets')->whereNull('deleted_at')
            ->whereNotNull('project')->where('project', '!=', '')
            ->distinct()->orderBy('project')->pluck('project');
    }

    // Resolves the period to [key, label, start, end, previous start, previous end].
    private function range(Request $request): array
    {
        $key = array_key_exists((string) $request->input('period'), self::PERIODS) ? $request->input('period') : 'month';
        $now = CarbonImmutable::now();

        [$start, $end] = match ($key) {
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            '3m'    => [$now->subMonths(2)->startOfMonth(), $now->endOfDay()],
            'year'  => [$now->startOfYear(), $now->endOfDay()],
            '12m'   => [$now->subMonths(11)->startOfMonth(), $now->endOfDay()],
            'all'   => [null, null],
            'custom'=> [
                $request->date('from') ? CarbonImmutable::parse($request->date('from'))->startOfDay() : $now->startOfMonth(),
                $request->date('to') ? CarbonImmutable::parse($request->date('to'))->endOfDay() : $now->endOfDay(),
            ],
        };

        if ($start && $end && $start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        // Comparison window: the same length immediately before.
        $prevStart = $prevEnd = null;
        if ($start && $end) {
            $days = (int) $start->diffInDays($end->startOfDay()) + 1;
            $prevEnd = $start->subDay()->endOfDay();
            $prevStart = $prevEnd->subDays($days - 1)->startOfDay();
        }

        $label = $key === 'custom' ? "{$start->format('d M Y')} – {$end->format('d M Y')}" : self::PERIODS[$key];

        return compact('key', 'label', 'start', 'end', 'prevStart', 'prevEnd');
    }

    private function data(Request $request): array
    {
        $programme = trim((string) $request->input('programme', ''));
        $service   = trim((string) $request->input('service', ''));
        $genderFilter = array_key_exists((string) $request->input('gender'), self::GENDERS) ? $request->input('gender') : '';
        $province  = array_key_exists((string) $request->input('province'), self::PROVINCE_DISTRICTS) ? $request->input('province') : '';
        $district  = trim((string) $request->input('district', ''));
        $age       = array_key_exists((string) $request->input('age'), self::AGE_GROUPS) ? $request->input('age') : '';
        $r = $this->range($request);

        $base = fn ($start = null, $end = null) => DB::table('tickets')->whereNull('deleted_at')
            ->when($programme, fn ($q) => $q->where('project', $programme))
            ->when($service, fn ($q) => $q->where('services_requested', $service))
            ->when($genderFilter, fn ($q) => $q->where('caller_gender', $genderFilter))
            ->when($province, fn ($q) => $q->where('province', $province))
            ->when($district, fn ($q) => $q->where('district', $district))
            ->when($age, fn ($q) => match ($age) {
                'u18'   => $q->whereBetween('caller_age', [1, 17]),
                '18-24' => $q->whereBetween('caller_age', [18, 24]),
                '25-34' => $q->whereBetween('caller_age', [25, 34]),
                '35-44' => $q->whereBetween('caller_age', [35, 44]),
                '45p'   => $q->where('caller_age', '>=', 45),
            })
            ->when($start && $end, fn ($q) => $q->whereBetween('created_at', [$start, $end]));

        $q = fn () => $base($r['start'], $r['end']);
        $filled = fn ($query, string $col) => $query->whereNotNull($col)->where($col, '!=', '');

        $summary = $q()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN call_validity = 'valid' THEN 1 ELSE 0 END) as valid,
            SUM(CASE WHEN uptake_confirmed = 1 THEN 1 ELSE 0 END) as uptake,
            SUM(CASE WHEN referred_to IS NOT NULL AND referred_to != '' THEN 1 ELSE 0 END) as referred,
            SUM(CASE WHEN is_repeat_caller = 1 THEN 1 ELSE 0 END) as repeat_callers
        ")->first();
        $total = (int) $summary->total;

        $previousTotal = $r['prevStart'] ? $base($r['prevStart'], $r['prevEnd'])->count() : null;

        // ── Calls by Case Type (purpose_of_call), top 10 + Other ─────────────
        $caseTypesAll = $filled($q(), 'purpose_of_call')
            ->select('purpose_of_call as name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('purpose_of_call')->orderByDesc('cnt')->get();
        $caseTypes = $caseTypesAll->take(10)->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->cnt])->values();
        if ($caseTypesAll->count() > 10) {
            $caseTypes->push(['name' => 'Other', 'count' => (int) $caseTypesAll->slice(10)->sum('cnt')]);
        }
        $caseTypeRecorded = (int) $caseTypesAll->sum('cnt');

        // ── Referral By Service: tickets per service vs confirmed uptake ──────
        $services = $filled($q(), 'services_requested')
            ->select('services_requested as name', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(CASE WHEN uptake_confirmed = 1 THEN 1 ELSE 0 END) as uptake'))
            ->groupBy('services_requested')->orderByDesc('cnt')->limit(12)->get()
            ->map(fn ($row) => [
                'name'     => $row->name,
                'referred' => (int) $row->cnt,
                'uptake'   => (int) $row->uptake,
                'rate'     => $row->cnt > 0 ? round($row->uptake / $row->cnt * 100) : 0,
            ]);

        // ── Demographics ─────────────────────────────────────────────────────
        $gender = $filled($q(), 'caller_gender')
            ->select(DB::raw('LOWER(caller_gender) as g'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('LOWER(caller_gender)'))->orderByDesc('cnt')->get()
            ->map(fn ($row) => ['name' => ucfirst(str_replace('_', ' ', $row->g)), 'count' => (int) $row->cnt])->values();

        $ages = $q()->selectRaw("
            SUM(CASE WHEN caller_age BETWEEN 1 AND 17 THEN 1 ELSE 0 END) as a1,
            SUM(CASE WHEN caller_age BETWEEN 18 AND 24 THEN 1 ELSE 0 END) as a2,
            SUM(CASE WHEN caller_age BETWEEN 25 AND 34 THEN 1 ELSE 0 END) as a3,
            SUM(CASE WHEN caller_age BETWEEN 35 AND 44 THEN 1 ELSE 0 END) as a4,
            SUM(CASE WHEN caller_age >= 45 THEN 1 ELSE 0 END) as a5
        ")->first();
        $ageGroups = collect(['Under 18' => 'a1', '18–24' => 'a2', '25–34' => 'a3', '35–44' => 'a4', '45+' => 'a5'])
            ->map(fn ($col, $name) => ['name' => $name, 'count' => (int) $ages->{$col}])->values();

        // Same youth bands as /screen's "Call Group by Gender & Age".
        $bands = ['10-14', '15-19', '20-25', '25+'];
        $ageGenderRows = $filled($q(), 'caller_gender')->whereBetween('caller_age', [10, 120])
            ->selectRaw("
                CASE
                    WHEN caller_age BETWEEN 10 AND 14 THEN '10-14'
                    WHEN caller_age BETWEEN 15 AND 19 THEN '15-19'
                    WHEN caller_age BETWEEN 20 AND 25 THEN '20-25'
                    ELSE '25+'
                END as band,
                LOWER(caller_gender) as g,
                COUNT(*) as cnt
            ")
            ->groupBy('band', DB::raw('LOWER(caller_gender)'))->get();
        $ageGender = collect($bands)->map(fn ($band) => [
            'band'   => $band,
            'male'   => (int) $ageGenderRows->where('band', $band)->where('g', 'male')->sum('cnt'),
            'female' => (int) $ageGenderRows->where('band', $band)->where('g', 'female')->sum('cnt'),
            'other'  => (int) $ageGenderRows->where('band', $band)->whereNotIn('g', ['male', 'female'])->sum('cnt'),
        ])->values();

        return [
            'filters' => [
                'programme' => $programme,
                'period'    => $r['key'],
                'from'      => $r['start']?->toDateString(),
                'to'        => $r['end']?->toDateString(),
                'service'   => $service,
                'gender'    => $genderFilter,
                'province'  => $province,
                'district'  => $district,
                'age'       => $age,
            ],
            // Human-readable list of the active filters (for the PDF header).
            'activeFilters' => array_filter([
                'Service'   => $service,
                'Gender'    => self::GENDERS[$genderFilter] ?? null,
                'Province'  => $province,
                'District'  => $district,
                'Age group' => self::AGE_GROUPS[$age] ?? null,
            ]),
            'periodLabel' => $r['label'],
            'summary' => [
                'total'          => $total,
                'previous_total' => $previousTotal,
                'valid'          => (int) $summary->valid,
                'referred'       => (int) $summary->referred,
                'uptake'         => (int) $summary->uptake,
                'repeat_callers' => (int) $summary->repeat_callers,
            ],
            'trend'            => $this->trend($q(), $r),
            'caseTypes'        => $caseTypes,
            'caseTypeRecorded' => $caseTypeRecorded,
            'services'         => $services,
            'gender'           => $gender,
            'ageGroups'        => $ageGroups,
            'ageGender'        => $ageGender,
        ];
    }

    // Cases per day for windows up to ~2 months, otherwise per month; empty
    // buckets are kept so the chart shows gaps as zero.
    private function trend($query, array $r): array
    {
        $start = $r['start'] ?? CarbonImmutable::parse((clone $query)->min('created_at') ?? now())->startOfMonth();
        $end   = $r['end'] ?? CarbonImmutable::now()->endOfDay();
        $daily = $start->diffInDays($end) <= 62;

        $sqlite = DB::getDriverName() === 'sqlite';
        $expr = $daily
            ? ($sqlite ? "strftime('%Y-%m-%d', created_at)" : 'DATE(created_at)')
            : ($sqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')");

        $counts = (clone $query)->selectRaw("{$expr} as bucket, COUNT(*) as cnt")
            ->groupBy('bucket')->pluck('cnt', 'bucket');

        $points = [];
        $cursor = $daily ? $start->startOfDay() : $start->startOfMonth();
        while ($cursor <= $end) {
            $key = $daily ? $cursor->toDateString() : $cursor->format('Y-m');
            $points[] = ['label' => $daily ? $cursor->format('d M') : $cursor->format('M Y'), 'count' => (int) ($counts[$key] ?? 0)];
            $cursor = $daily ? $cursor->addDay() : $cursor->addMonth();
        }

        return ['unit' => $daily ? 'day' : 'month', 'points' => $points];
    }
}
