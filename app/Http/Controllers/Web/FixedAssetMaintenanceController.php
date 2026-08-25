<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\FixedAssetMaintenance;
use App\Models\User;
use App\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FixedAssetMaintenanceController extends Controller
{
    public function __construct(private readonly FixedAssetService $service)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function store(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'maintenance_type'   => 'required|string|in:routine_service,repair,inspection,calibration,cleaning,upgrade,preventive,corrective',
            'description'        => 'nullable|string',
            'service_provider'   => 'nullable|string|max:255',
            'service_date'       => 'required|date',
            'cost'               => 'nullable|numeric|min:0',
            'next_service_date'  => 'nullable|date',
            'performed_by'       => 'nullable|exists:users,id',
            'notes'              => 'nullable|string',
        ]);

        try {
            $this->service->scheduleMaintenance($fixedAsset, $request->user(), $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Maintenance scheduled.');
    }

    public function complete(Request $request, FixedAsset $fixedAsset, FixedAssetMaintenance $maintenance): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate(['notes' => 'nullable|string']);

        try {
            $this->service->completeMaintenance($maintenance, $request->user(), $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Maintenance completed.');
    }

    public function cancel(Request $request, FixedAsset $fixedAsset, FixedAssetMaintenance $maintenance): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        try {
            $this->service->cancelMaintenance($maintenance, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Maintenance cancelled.');
    }
}
