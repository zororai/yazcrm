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
        $today        = Carbon::today();
        $monthStart   = $today->copy()->startOfMonth();
        $sixMonthsAgo = $today->copy()->subMonths(5)->startOfMonth();

        // Month labels for the chart
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = $today->copy()->subMonths($i)->format('M y');
        }

        // All-time totals per project
        $totals = DB::table('tickets')
            ->selectRaw('project, COUNT(*) as cnt')
            ->whereNotNull('project')->where('project', '!=', '')
            ->groupBy('project')
            ->pluck('cnt', 'project');

        // This-month totals
        $thisMonth = DB::table('tickets')
            ->selectRaw('project, COUNT(*) as cnt')
            ->whereNotNull('project')->where('project', '!=', '')
            ->where('created_at', '>=', $monthStart)
            ->groupBy('project')
            ->pluck('cnt', 'project');

        // Monthly counts per project (last 6 months)
        $monthlyCounts = DB::table('tickets')
            ->selectRaw("project, DATE_FORMAT(created_at, '%Y-%m') as m, COUNT(*) as cnt")
            ->whereNotNull('project')->where('project', '!=', '')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('project', 'm')
            ->get()
            ->groupBy('project')
            ->map(fn ($rows) => $rows->pluck('cnt', 'm'));

        // Purpose breakdown per project
        $purposes = DB::table('tickets')
            ->selectRaw('project, COALESCE(NULLIF(purpose_of_call,""),"Unknown") as purpose, COUNT(*) as cnt')
            ->whereNotNull('project')->where('project', '!=', '')
            ->groupBy('project', 'purpose')
            ->orderByDesc('cnt')
            ->get()
            ->groupBy('project')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['purpose' => $r->purpose, 'count' => (int) $r->cnt])->values());

        // Services requested breakdown per project (top 5)
        $services = DB::table('tickets')
            ->selectRaw('project, COALESCE(NULLIF(services_requested,""),"Unknown") as service, COUNT(*) as cnt')
            ->whereNotNull('project')->where('project', '!=', '')
            ->groupBy('project', 'service')
            ->orderByDesc('cnt')
            ->get()
            ->groupBy('project')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['service' => $r->service, 'count' => (int) $r->cnt])->take(5)->values());

        $items = LookupItem::where('type', 'project')->orderBy('sort_order')->orderBy('name')->get();

        $projects = $items->map(function ($item) use ($today, $months, $totals, $thisMonth, $monthlyCounts, $purposes, $services) {
            $monthly = [];
            for ($i = 5; $i >= 0; $i--) {
                $key      = $today->copy()->subMonths($i)->format('Y-m');
                $monthly[] = (int) ($monthlyCounts[$item->name][$key] ?? 0);
            }

            return [
                'id'         => $item->id,
                'name'       => $item->name,
                'sort_order' => $item->sort_order,
                'is_active'  => $item->is_active,
                'total'      => (int) ($totals[$item->name] ?? 0),
                'this_month' => (int) ($thisMonth[$item->name] ?? 0),
                'monthly'    => $monthly,
                'purposes'   => ($purposes[$item->name] ?? collect())->all(),
                'services'   => ($services[$item->name] ?? collect())->all(),
            ];
        })->all();

        return Inertia::render('DistressDomains/ProjectStats', [
            'projects' => $projects,
            'items'    => $items,
            'months'   => $months,
        ]);
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
