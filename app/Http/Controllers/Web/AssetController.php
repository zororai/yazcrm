<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetActivityLog;
use App\Models\ItAssetCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    private array $enumOptions = [
        'types'               => ['server', 'network', 'endpoint', 'telephony', 'application', 'power', 'saas'],
        'patchStatuses'       => ['current', 'outdated', 'eol', 'unknown'],
        'dataSensitivities'   => ['none', 'internal', 'sensitive'],
        'criticalities'       => ['low', 'medium', 'high'],
        'sources'             => ['scan', 'manual'],
        'lifecycleStatuses'   => ['active', 'in_repair', 'retired', 'disposed'],
    ];

    public function create(): Response
    {
        return Inertia::render('Registry/AssetForm', [
            'asset'      => null,
            'options'    => $this->enumOptions,
            'categories' => ItAssetCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $asset = Asset::create($data);

        $this->log($asset, $request->user(), 'created');

        return redirect()->route('registry.index')->with('success', 'Asset created.');
    }

    public function show(Asset $asset): Response
    {
        $asset->load(['risks.controls', 'risks.priorityAction', 'category', 'activityLogs.user:id,name']);

        return Inertia::render('Registry/AssetShow', [
            'asset' => $asset,
        ]);
    }

    public function edit(Asset $asset): Response
    {
        return Inertia::render('Registry/AssetForm', [
            'asset'      => $asset,
            'options'    => $this->enumOptions,
            'categories' => ItAssetCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate($this->rules($asset->id));

        $asset->update($data);
        $changed = array_keys($asset->getChanges());

        if ($changed) {
            $this->log($asset, $request->user(), 'updated', $changed);
        }

        return redirect()->route('registry.index')->with('success', 'Asset updated.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        $this->log($asset, $request->user(), 'deleted');
        $asset->delete();

        return redirect()->route('registry.index')->with('success', 'Asset deleted.');
    }

    public function restore(Request $request, int $asset): RedirectResponse
    {
        $model = Asset::onlyTrashed()->findOrFail($asset);
        $model->restore();

        $this->log($model, $request->user(), 'restored');

        return back()->with('success', 'Asset restored.');
    }

    public function forceDestroy(Request $request, int $asset): RedirectResponse
    {
        $model = Asset::onlyTrashed()->findOrFail($asset);
        $model->forceDelete();

        return back()->with('success', 'Asset permanently deleted.');
    }

    private function log(Asset $asset, User $actor, string $action, ?array $changedFields = null): void
    {
        AssetActivityLog::create([
            'asset_id'       => $asset->id,
            'user_id'        => $actor->id,
            'action'         => $action,
            'changed_fields' => $changedFields,
        ]);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'asset_tag'          => 'required|string|max:100|unique:assets,asset_tag' . ($ignoreId ? ",{$ignoreId}" : ''),
            'name'               => 'required|string|max:255',
            'type'               => 'required|in:server,network,endpoint,telephony,application,power,saas',
            'category_id'        => 'nullable|exists:it_asset_categories,id',
            'location'           => 'nullable|string|max:255',
            'ip_address'         => 'nullable|string|max:100',
            'owner'              => 'nullable|string|max:255',
            'os_version'         => 'nullable|string|max:255',
            'patch_status'       => 'nullable|in:current,outdated,eol,unknown',
            'data_sensitivity'   => 'nullable|in:none,internal,sensitive',
            'criticality_393'    => 'nullable|in:low,medium,high',
            'source'             => 'nullable|in:scan,manual',
            'last_scanned_at'    => 'nullable|date',
            'serial_number'      => 'nullable|string|max:255',
            'supplier'           => 'nullable|string|max:255',
            'acquired_on'        => 'nullable|date',
            'cost'               => 'nullable|numeric|min:0',
            'warranty_expires_on' => 'nullable|date',
            'lifecycle_status'   => 'nullable|in:active,in_repair,retired,disposed',
            'replace_due_on'     => 'nullable|date',
            'notes'              => 'nullable|string',
        ];
    }
}
