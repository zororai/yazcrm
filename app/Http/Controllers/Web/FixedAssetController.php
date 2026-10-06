<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\FixedAssetRevaluation;
use App\Models\Location;
use App\Models\User;
use App\Services\FixedAssetService;
use App\Support\Assets\AssetStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $service)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'stores', 'accounting_dep'], true);
    }

    private function filteredAssetsQuery(Request $request)
    {
        $search = $request->string('search')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $warrantyExpiring = $request->boolean('warranty_expiring');

        return FixedAsset::with(['category:id,name', 'custodian:id,name', 'department:id,name', 'location:id,name'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            }))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($warrantyExpiring, fn ($q) => $q->whereNotNull('warranty_expiry')
                ->whereBetween('warranty_expiry', [now(), now()->addDays(90)]))
            ->orderByDesc('created_at');
    }

    public function index(Request $request): Response
    {
        $assets = $this->filteredAssetsQuery($request)->get();

        if ($request->boolean('revaluation_due')) {
            $assets = $assets->filter(fn (FixedAsset $a) => $a->revaluation_due)->values();
        }

        return Inertia::render('FixedAssets/Index', [
            'assets'     => $assets,
            'categories' => AssetCategory::orderBy('name')->get(['id', 'name']),
            'isManager'  => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'asset_category_id' => 'nullable|exists:asset_categories,id',
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'manufacturer'      => 'nullable|string|max:255',
            'model'             => 'nullable|string|max:255',
            'serial_number'     => 'nullable|string|max:255|unique:fixed_assets,serial_number',
            'purchase_date'     => 'nullable|date',
            'purchase_cost'     => 'nullable|numeric|min:0',
            'useful_life_years' => 'nullable|integer|min:1|max:100',
            'salvage_value'     => 'nullable|numeric|min:0',
            'revaluation_cycle_years' => 'nullable|integer|min:1|max:50',
            'supplier_name'     => 'nullable|string|max:255',
            'warranty_start'    => 'nullable|date',
            'warranty_expiry'   => 'nullable|date',
            'condition'         => 'nullable|string|in:'.implode(',', AssetStatus::CONDITIONS),
        ]);

        $asset = $this->service->registerAsset($request->user(), $data);

        return redirect()->route('fixed-assets.show', $asset)->with('success', 'Asset registered.');
    }

    public function show(Request $request, FixedAsset $fixedAsset): Response
    {
        return Inertia::render('FixedAssets/Show', [
            'asset'       => $fixedAsset->load(['category:id,name', 'custodian:id,name', 'department:id,name', 'location:id,name']),
            'assignments' => $fixedAsset->assignments()->with(['assignee:id,name', 'assignedBy:id,name', 'department:id,name', 'location:id,name'])->get(),
            'activityLogs' => $fixedAsset->activityLogs()->with('user:id,name')->get(),
            'maintenanceRecords' => $fixedAsset->maintenanceRecords()->with(['performedBy:id,name', 'creator:id,name'])->get(),
            'inspections' => $fixedAsset->inspections()->with('inspector:id,name')->get(),
            'revaluations' => $fixedAsset->revaluations()->with('revaluedBy:id,name')->get(),
            'users'       => User::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'locations'   => Location::orderBy('name')->get(['id', 'name']),
            'isManager'   => $this->isManager($request->user()),
        ]);
    }

    public function update(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'manufacturer'      => 'nullable|string|max:255',
            'model'             => 'nullable|string|max:255',
            'purchase_cost'     => 'nullable|numeric|min:0',
            'useful_life_years' => 'nullable|integer|min:1|max:100',
            'salvage_value'     => 'nullable|numeric|min:0',
            'revaluation_cycle_years' => 'nullable|integer|min:1|max:50',
            'warranty_expiry'   => 'nullable|date',
        ]);

        $this->service->updateAsset($fixedAsset, $request->user(), $data);

        return back()->with('success', 'Saved.');
    }

    public function assign(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'assigned_to'   => 'required|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'location_id'   => 'nullable|exists:locations,id',
            'notes'         => 'nullable|string',
        ]);

        try {
            $this->service->assignAsset($fixedAsset, $request->user(), $data['assigned_to'], $data['department_id'] ?? null, $data['location_id'] ?? null, $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Asset assigned.');
    }

    public function returnAsset(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'condition' => 'required|string|in:'.implode(',', AssetStatus::CONDITIONS),
            'notes'     => 'nullable|string',
        ]);

        try {
            $this->service->returnAsset($fixedAsset, $request->user(), $data['condition'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Asset returned.');
    }

    public function transfer(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'location_id'   => 'nullable|exists:locations,id',
            'notes'         => 'nullable|string',
        ]);

        try {
            $this->service->transferAsset($fixedAsset, $request->user(), $data['department_id'] ?? null, $data['location_id'] ?? null, $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Asset transferred.');
    }

    public function dispose(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $this->service->disposeAsset($fixedAsset, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Asset disposed.');
    }

    public function exportExcel(Request $request)
    {
        $assets = $this->filteredAssetsQuery($request)->get();

        $headers = [
            'Asset #', 'Name', 'Category', 'Custodian', 'Department', 'Status',
            'Purchase Date', 'Purchase Cost', 'Useful Life (yrs)', 'Salvage Value',
            'Annual Depreciation', 'Accumulated Depreciation', 'Book Value',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($assets as $a) {
            fputcsv($handle, [
                $a->asset_number,
                $a->name,
                $a->category?->name ?? '',
                $a->custodian?->name ?? '',
                $a->department?->name ?? '',
                str_replace('_', ' ', $a->status),
                $a->purchase_date?->format('Y-m-d') ?? '',
                $a->purchase_cost ?? '',
                $a->useful_life_years ?? '',
                $a->salvage_value ?? '',
                $a->annual_depreciation ?? '',
                $a->accumulated_depreciation ?? '',
                $a->book_value ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'fixed-assets-' . now()->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $assets = $this->filteredAssetsQuery($request)->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.fixed-assets-pdf', [
            'assets'      => $assets,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('fixed-assets-' . now()->format('Y-m-d') . '.pdf');
    }

    public function storeRevaluation(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'revaluation_date'      => 'required|date',
            'revalued_amount'       => 'required|numeric|min:0',
            'new_useful_life_years' => 'required|integer|min:1|max:100',
            'new_salvage_value'     => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string|max:2000',
        ]);

        $this->service->revalueAsset($fixedAsset, $request->user(), $data);

        return back()->with('success', 'Revaluation recorded.');
    }

    private function revaluationRows(Request $request)
    {
        $assets = FixedAsset::with(['category:id,name', 'custodian:id,name', 'department:id,name'])
            ->orderByDesc('created_at')
            ->get();

        if ($request->boolean('due_only')) {
            $assets = $assets->filter(fn (FixedAsset $a) => $a->revaluation_due)->values();
        }

        return $assets;
    }

    public function revaluationsIndex(Request $request): Response
    {
        return Inertia::render('FixedAssets/Revaluations', [
            'assets'    => $this->revaluationRows($request),
            'dueOnly'   => $request->boolean('due_only'),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function exportRevaluationsExcel(Request $request)
    {
        $assets = $this->revaluationRows($request);

        $headers = [
            'Asset #', 'Name', 'Category', 'Custodian', 'Department',
            'Revaluation Cycle (yrs)', 'Last Revalued', 'Next Due', 'Due Now',
            'Current Value', 'Book Value',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($assets as $a) {
            fputcsv($handle, [
                $a->asset_number,
                $a->name,
                $a->category?->name ?? '',
                $a->custodian?->name ?? '',
                $a->department?->name ?? '',
                $a->revaluation_cycle_years ?? '',
                $a->last_revalued_at?->format('Y-m-d') ?? 'Never',
                $a->next_revaluation_due ?? '',
                $a->revaluation_due ? 'Yes' : 'No',
                $a->current_value ?? '',
                $a->book_value ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'asset-revaluations-' . now()->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function exportRevaluationsPdf(Request $request)
    {
        $assets = $this->revaluationRows($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.fixed-asset-revaluations-pdf', [
            'assets'      => $assets,
            'generatedAt' => now(),
            'dueOnly'     => $request->boolean('due_only'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('asset-revaluations-' . now()->format('Y-m-d') . '.pdf');
    }

    public function dashboard(Request $request): Response
    {
        return Inertia::render('FixedAssets/Dashboard', [
            ...$this->dashboardData($request),
            'categories'  => AssetCategory::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters'     => $request->only(['category_id', 'department_id']),
        ]);
    }

    public function exportDashboardPdf(Request $request)
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.fixed-asset-dashboard-pdf', [
            ...$this->dashboardData($request),
            'generatedAt'    => now(),
            'categoryName'   => $request->filled('category_id') ? AssetCategory::find($request->integer('category_id'))?->name : null,
            'departmentName' => $request->filled('department_id') ? Department::find($request->integer('department_id'))?->name : null,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('fixed-asset-dashboard-' . now()->format('Y-m-d') . '.pdf');
    }

    // Figures shared by the dashboard page and its PDF. Value totals exclude
    // disposed assets (they are off the books); counts by status include them.
    private function dashboardData(Request $request): array
    {
        $assets = FixedAsset::with(['category:id,name'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('asset_category_id', $request->integer('category_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->get();

        $onBooks = $assets->where('status', '!=', AssetStatus::DISPOSED);

        $baseCost = fn (FixedAsset $a) => (float) ($a->book_value ?? 0) + (float) ($a->accumulated_depreciation ?? 0);

        $byCategory = $onBooks->groupBy(fn (FixedAsset $a) => $a->category?->name ?? 'Uncategorised')
            ->map(fn ($group, $name) => [
                'name'                     => $name,
                'count'                    => $group->count(),
                'cost'                     => round($group->sum($baseCost), 2),
                'book_value'               => round($group->sum('book_value'), 2),
                'accumulated_depreciation' => round($group->sum('accumulated_depreciation'), 2),
                'annual_depreciation'      => round($group->sum('annual_depreciation'), 2),
            ])
            ->sortByDesc('book_value')
            ->values();

        $byStatus = $assets->countBy('status')
            ->map(fn ($count, $status) => ['status' => $status, 'count' => $count])
            ->sortByDesc('count')
            ->values();

        $today = now()->startOfDay();
        $revalWindowEnd = $today->copy()->addDays(90);
        $upcomingRevaluations = $onBooks
            ->filter(fn (FixedAsset $a) => $a->next_revaluation_due && $a->next_revaluation_due <= $revalWindowEnd->toDateString())
            ->sortBy('next_revaluation_due')
            ->map(fn (FixedAsset $a) => [
                'id'           => $a->id,
                'asset_number' => $a->asset_number,
                'name'         => $a->name,
                'category'     => $a->category?->name,
                'due'          => $a->next_revaluation_due,
                'overdue'      => $a->revaluation_due,
                'book_value'   => $a->book_value,
            ])
            ->values();

        $recentRevaluations = FixedAssetRevaluation::with(['asset:id,asset_number,name', 'revaluedBy:id,name'])
            ->whereIn('fixed_asset_id', $assets->pluck('id'))
            ->where('revaluation_date', '>=', $today->copy()->subYear())
            ->latest('revaluation_date')
            ->get();

        return [
            'summary' => [
                'asset_count'              => $onBooks->count(),
                'disposed_count'           => $assets->count() - $onBooks->count(),
                'cost'                     => round($onBooks->sum($baseCost), 2),
                'book_value'               => round($onBooks->sum('book_value'), 2),
                'accumulated_depreciation' => round($onBooks->sum('accumulated_depreciation'), 2),
                'annual_depreciation'      => round($onBooks->sum('annual_depreciation'), 2),
                'revaluations_overdue'     => $upcomingRevaluations->where('overdue', true)->count(),
                'revaluations_due_soon'    => $upcomingRevaluations->where('overdue', false)->count(),
                'revaluation_change_12m'   => round($recentRevaluations->sum(fn ($r) => (float) $r->revalued_amount - (float) $r->previous_value), 2),
                'revaluation_count_12m'    => $recentRevaluations->count(),
            ],
            'byCategory'           => $byCategory,
            'byStatus'             => $byStatus,
            'projection'           => $this->bookValueProjection($onBooks),
            'upcomingRevaluations' => $upcomingRevaluations->take(15)->values(),
            'recentRevaluations'   => $recentRevaluations->take(10)->map(fn ($r) => [
                'date'           => $r->revaluation_date?->toDateString(),
                'asset_number'   => $r->asset?->asset_number,
                'name'           => $r->asset?->name,
                'previous_value' => (float) $r->previous_value,
                'revalued_amount'=> (float) $r->revalued_amount,
                'change'         => round((float) $r->revalued_amount - (float) $r->previous_value, 2),
                'by'             => $r->revaluedBy?->name,
            ])->values(),
        ];
    }

    // Total book value today and on this date in each of the next 5 years,
    // using the same straight-line rule as FixedAsset (no future revaluations).
    private function bookValueProjection($assets): array
    {
        $points = [];

        foreach (range(0, 5) as $offset) {
            $at = now()->addYears($offset);
            $total = 0.0;

            foreach ($assets as $a) {
                $annual = $a->annual_depreciation;
                $base = (float) ($a->book_value ?? 0) + (float) ($a->accumulated_depreciation ?? 0);
                $baseDate = $a->depreciation_base_date ?? $a->purchase_date;

                if ($annual === null || ! $baseDate) {
                    $total += $base;
                    continue;
                }

                $elapsed = $at->lessThan($baseDate) ? 0 : min($baseDate->floatDiffInYears($at), $a->useful_life_years);
                $depreciable = max($base - (float) ($a->salvage_value ?? 0), 0);
                // Rounded per asset, like FixedAsset::book_value, so today's point matches the register.
                $total += round($base - min($annual * $elapsed, $depreciable), 2);
            }

            $points[] = ['year' => (int) $at->format('Y'), 'book_value' => round($total, 2)];
        }

        return $points;
    }

    public function depreciationReport(Request $request): Response
    {
        return Inertia::render('FixedAssets/DepreciationReport', [
            'assets'    => $this->filteredAssetsQuery($request)->get(),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function exportDepreciationExcel(Request $request)
    {
        $assets = $this->filteredAssetsQuery($request)->get();

        $headers = ['Asset Name', 'Salvage Value', 'Annual Depreciation', 'Accumulated Depreciation', 'Book Value'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($assets as $a) {
            fputcsv($handle, [
                $a->name,
                $a->salvage_value ?? '',
                $a->annual_depreciation ?? '',
                $a->accumulated_depreciation ?? '',
                $a->book_value ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'depreciation-report-' . now()->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function exportDepreciationPdf(Request $request)
    {
        $assets = $this->filteredAssetsQuery($request)->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.depreciation-report-pdf', [
            'assets'      => $assets,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('depreciation-report-' . now()->format('Y-m-d') . '.pdf');
    }
}
