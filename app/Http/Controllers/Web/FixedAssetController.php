<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\FixedAsset;
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
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $warrantyExpiring = $request->boolean('warranty_expiring');

        return Inertia::render('FixedAssets/Index', [
            'assets' => FixedAsset::with(['category:id,name', 'custodian:id,name', 'department:id,name', 'location:id,name'])
                ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('asset_number', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                }))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($warrantyExpiring, fn ($q) => $q->whereNotNull('warranty_expiry')
                    ->whereBetween('warranty_expiry', [now(), now()->addDays(90)]))
                ->orderByDesc('created_at')
                ->get(),
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
}
