<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DistressDomain;
use App\Models\LookupItem;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DistressDomainController extends Controller
{
    public function index(): Response
    {
        $sections = [
            ['type' => 'distress-domains', 'label' => 'Distress Domains', 'count' => DistressDomain::count()],
        ];

        foreach (LookupItem::TYPES as $type => $label) {
            $sections[] = ['type' => $type, 'label' => $label, 'count' => LookupItem::where('type', $type)->count()];
        }

        return Inertia::render('DistressDomains/Index', ['sections' => $sections]);
    }

    public function section(string $type): Response
    {
        if ($type === 'distress-domains') {
            return Inertia::render('DistressDomains/Section', [
                'type'     => 'distress-domains',
                'label'    => 'Distress Domains',
                'items'    => DistressDomain::orderBy('sort_order')->orderBy('name')->get(),
                'isLookup' => false,
            ]);
        }

        if ($type === 'project') {
            return $this->projectSection();
        }

        abort_unless(array_key_exists($type, LookupItem::TYPES), 404);

        return Inertia::render('DistressDomains/Section', [
            'type'     => $type,
            'label'    => LookupItem::TYPES[$type],
            'items'    => LookupItem::where('type', $type)->orderBy('sort_order')->orderBy('name')->get(),
            'isLookup' => true,
        ]);
    }

    private function projectSection(): Response
    {
        $filter      = request('filter', 'all');
        $filterMonth = request('month');
        $filterYear  = request('year', (string) Carbon::today()->year);
        $today       = Carbon::today();

        // ── Build chart slots & period range based on filter ─────────────
        [$periodStart, $periodEnd, $periodLabel, $chartSlots, $groupExpr] =
            $this->resolveProjectFilter($filter, $filterMonth, $filterYear, $today);

        $chartKeys   = array_column($chartSlots, 'key');
        $chartLabels = array_column($chartSlots, 'label');
        $chartFrom   = $chartSlots[0]['start'];
        $chartTo     = end($chartSlots)['end'];

        // ── All-time totals (always displayed) ────────────────────────────
        $allTimeTotals = DB::table('tickets')
            ->selectRaw('project, COUNT(*) as cnt')
            ->whereNotNull('project')->where('project', '!=', '')
            ->groupBy('project')->pluck('cnt', 'project');

        // ── Period totals (filtered) ──────────────────────────────────────
        $periodTotals = ($filter === 'all') ? $allTimeTotals : DB::table('tickets')
            ->selectRaw('project, COUNT(*) as cnt')
            ->whereNotNull('project')->where('project', '!=', '')
            ->where('created_at', '>=', $periodStart)
            ->where('created_at', '<=', $periodEnd)
            ->groupBy('project')->pluck('cnt', 'project');

        // ── Chart counts per slot ─────────────────────────────────────────
        $chartCounts = DB::table('tickets')
            ->selectRaw("project, {$groupExpr} as slot, COUNT(*) as cnt")
            ->whereNotNull('project')->where('project', '!=', '')
            ->where('created_at', '>=', $chartFrom)
            ->where('created_at', '<=', $chartTo)
            ->groupBy('project', 'slot')
            ->get()->groupBy('project')
            ->map(fn ($rows) => $rows->pluck('cnt', 'slot'));

        // ── Purpose & services breakdowns (respect period filter) ─────────
        $breakdownBase = fn () => DB::table('tickets')
            ->whereNotNull('project')->where('project', '!=', '')
            ->when($filter !== 'all', fn ($q) => $q
                ->where('created_at', '>=', $periodStart)
                ->where('created_at', '<=', $periodEnd));

        $purposes = $breakdownBase()
            ->selectRaw('project, COALESCE(NULLIF(purpose_of_call,""),"Unknown") as purpose, COUNT(*) as cnt')
            ->groupBy('project', 'purpose')->orderByDesc('cnt')
            ->get()->groupBy('project')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['purpose' => $r->purpose, 'count' => (int) $r->cnt])->values());

        $services = $breakdownBase()
            ->selectRaw('project, COALESCE(NULLIF(services_requested,""),"Unknown") as service, COUNT(*) as cnt')
            ->groupBy('project', 'service')->orderByDesc('cnt')
            ->get()->groupBy('project')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['service' => $r->service, 'count' => (int) $r->cnt])->take(5)->values());

        // ── Years list for the year dropdown ─────────────────────────────
        $minYear = (int) (DB::table('tickets')->whereNotNull('project')->min(DB::raw('YEAR(created_at)')) ?: $today->year);
        $years   = range($minYear, $today->year);

        $items = LookupItem::where('type', 'project')->orderBy('sort_order')->orderBy('name')->get();

        $projects = $items->map(function ($item) use ($chartKeys, $chartCounts, $allTimeTotals, $periodTotals, $purposes, $services) {
            return [
                'id'           => $item->id,
                'name'         => $item->name,
                'sort_order'   => $item->sort_order,
                'is_active'    => $item->is_active,
                'total'        => (int) ($allTimeTotals[$item->name] ?? 0),
                'period_total' => (int) ($periodTotals[$item->name] ?? 0),
                'monthly'      => array_map(fn ($k) => (int) ($chartCounts[$item->name][$k] ?? 0), $chartKeys),
                'purposes'     => ($purposes[$item->name] ?? collect())->all(),
                'services'     => ($services[$item->name] ?? collect())->all(),
            ];
        })->all();

        return Inertia::render('DistressDomains/ProjectStats', [
            'projects'    => $projects,
            'items'       => $items,
            'months'      => $chartLabels,
            'filter'      => $filter,
            'filterMonth' => $filterMonth ?? $today->format('Y-m'),
            'filterYear'  => (string) $filterYear,
            'periodLabel' => $periodLabel,
            'years'       => $years,
        ]);
    }

    private function resolveProjectFilter(string $filter, ?string $filterMonth, string $filterYear, Carbon $today): array
    {
        switch ($filter) {
            case 'today':
                $slots = [];
                for ($i = 6; $i >= 0; $i--) {
                    $d = $today->copy()->subDays($i);
                    $slots[] = ['label' => $d->format('D d'), 'key' => $d->toDateString(),
                                'start' => $d->copy()->startOfDay(), 'end' => $d->copy()->endOfDay()];
                }
                return [$today->copy()->startOfDay(), $today->copy()->endOfDay(),
                        'Today — ' . $today->format('d M Y'), $slots, "DATE(created_at)"];

            case 'month':
                $m = $filterMonth ? Carbon::createFromFormat('Y-m', $filterMonth)->startOfMonth() : $today->copy()->startOfMonth();
                $slots = [];
                for ($d = 1; $d <= $m->daysInMonth; $d++) {
                    $day = $m->copy()->day($d);
                    $slots[] = ['label' => (string) $d, 'key' => $day->toDateString(),
                                'start' => $day->copy()->startOfDay(), 'end' => $day->copy()->endOfDay()];
                }
                return [$m->copy()->startOfMonth(), $m->copy()->endOfMonth(),
                        $m->format('F Y'), $slots, "DATE(created_at)"];

            case 'year':
                $yr = Carbon::createFromFormat('Y', $filterYear)->startOfYear();
                $slots = [];
                for ($mo = 1; $mo <= 12; $mo++) {
                    $month = $yr->copy()->month($mo);
                    $slots[] = ['label' => $month->format('M'), 'key' => $month->format('Y-m'),
                                'start' => $month->copy()->startOfMonth(), 'end' => $month->copy()->endOfMonth()];
                }
                return [$yr->copy()->startOfYear(), $yr->copy()->endOfYear(),
                        $filterYear, $slots, "DATE_FORMAT(created_at, '%Y-%m')"];

            default: // all — last 6 months chart
                $slots = [];
                for ($i = 5; $i >= 0; $i--) {
                    $m = $today->copy()->subMonths($i);
                    $slots[] = ['label' => $m->format('M y'), 'key' => $m->format('Y-m'),
                                'start' => $m->copy()->startOfMonth(), 'end' => $m->copy()->endOfMonth()];
                }
                return [null, null, 'All Time', $slots, "DATE_FORMAT(created_at, '%Y-%m')"];
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255|unique:distress_domains,name',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        DistressDomain::create([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        return back()->with('success', 'Domain added.');
    }

    public function update(Request $request, DistressDomain $distressDomain): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255|unique:distress_domains,name,' . $distressDomain->id,
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $distressDomain->update([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? $distressDomain->sort_order,
            'is_active'  => $data['is_active'] ?? $distressDomain->is_active,
        ]);

        return back()->with('success', 'Domain updated.');
    }

    public function destroy(DistressDomain $distressDomain): RedirectResponse
    {
        $distressDomain->delete();
        return back()->with('success', 'Domain removed.');
    }
}
