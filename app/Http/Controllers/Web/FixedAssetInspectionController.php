<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\User;
use App\Services\FixedAssetService;
use App\Support\Assets\AssetStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FixedAssetInspectionController extends Controller
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
            'inspected_at'    => 'required|date',
            'condition'       => 'required|string|in:'.implode(',', AssetStatus::CONDITIONS),
            'working_status'  => 'required|string|in:working,not_working,partially_working',
            'damage_notes'    => 'nullable|string',
            'comments'        => 'nullable|string',
        ]);

        $this->service->recordInspection($fixedAsset, $request->user(), $data);

        return back()->with('success', 'Inspection recorded.');
    }
}
