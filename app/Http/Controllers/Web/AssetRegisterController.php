<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetActivityLog;
use App\Models\ItAssetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class AssetRegisterController extends Controller
{
    private const IMPORT_COLUMNS = [
        'asset_tag', 'name', 'type', 'location', 'ip_address', 'owner', 'os_version',
        'patch_status', 'data_sensitivity', 'criticality_393', 'source',
        'serial_number', 'supplier', 'acquired_on', 'cost', 'warranty_expires_on',
        'lifecycle_status', 'replace_due_on', 'notes',
    ];

    public function index(Request $request): \Inertia\Response
    {
        $trashed = $request->boolean('trashed');
        $query = $trashed ? Asset::onlyTrashed() : Asset::query();

        if ($request->filled('type'))             { $query->where('type', $request->type); }
        if ($request->filled('category_id'))      { $query->where('category_id', $request->category_id); }
        if ($request->filled('lifecycle_status')) { $query->where('lifecycle_status', $request->lifecycle_status); }
        if ($request->filled('data_sensitivity')) { $query->where('data_sensitivity', $request->data_sensitivity); }
        if ($request->filled('criticality_393'))  { $query->where('criticality_393', $request->criticality_393); }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('asset_tag', 'like', "%{$request->search}%")
                  ->orWhere('location', 'like', "%{$request->search}%")
                  ->orWhere('owner', 'like', "%{$request->search}%");
            });
        }

        $assets = $query->with('category:id,name')->latest()->paginate(25)->withQueryString();

        $summary = [
            'total'         => Asset::count(),
            'book_value'    => Asset::sum('cost'),
            'by_type'       => Asset::selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type'),
            'warranty_soon' => Asset::warrantyExpiring()->count(),
            'refresh_soon'  => Asset::dueRefresh()->count(),
            'in_repair'     => Asset::where('lifecycle_status', 'in_repair')->count(),
            'trashed'       => Asset::onlyTrashed()->count(),
        ];

        return Inertia::render('Registry/Index', [
            'assets'     => $assets,
            'summary'    => $summary,
            'categories' => ItAssetCategory::orderBy('name')->get(['id', 'name']),
            'filters'    => $request->only(['type', 'category_id', 'lifecycle_status', 'data_sensitivity', 'criticality_393', 'search', 'trashed']),
        ]);
    }

    public function export(): StreamedResponse
    {
        $assets = Asset::orderBy('asset_tag')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="asset-register-' . now()->format('Y-m-d') . '.csv"',
        ];

        $columns = self::IMPORT_COLUMNS;

        $callback = function () use ($assets, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            foreach ($assets as $asset) {
                $row = [];
                foreach ($columns as $col) {
                    $val = $asset->{$col};
                    if ($val instanceof \Carbon\Carbon) {
                        $val = $val->toDateString();
                    }
                    $row[] = $val;
                }
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importTemplate(): StreamedResponse
    {
        $columns = self::IMPORT_COLUMNS;

        return response()->stream(function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="asset-register-import-template.csv"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => trim(strtolower($h)), $header);

        $validEnums = [
            'type'             => ['server', 'network', 'endpoint', 'telephony', 'application', 'power', 'saas'],
            'patch_status'     => ['current', 'outdated', 'eol', 'unknown'],
            'data_sensitivity' => ['none', 'internal', 'sensitive'],
            'criticality_393'  => ['low', 'medium', 'high'],
            'source'           => ['scan', 'manual'],
            'lifecycle_status' => ['active', 'in_repair', 'retired', 'disposed'],
        ];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];
        $row     = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            $data = array_combine($header, $line);

            if (empty($data['asset_tag']) || empty($data['name']) || empty($data['type'])) {
                $skipped++;
                $errors[] = "Row {$row}: missing required asset_tag/name/type.";
                continue;
            }

            if (! in_array($data['type'], $validEnums['type'], true)) {
                $skipped++;
                $errors[] = "Row {$row}: invalid type '{$data['type']}'.";
                continue;
            }

            $payload = ['asset_tag' => $data['asset_tag'], 'name' => $data['name'], 'type' => $data['type']];

            foreach (self::IMPORT_COLUMNS as $col) {
                if ($col === 'asset_tag' || $col === 'name' || $col === 'type' || ! array_key_exists($col, $data)) {
                    continue;
                }
                $val = trim($data[$col] ?? '');
                if ($val === '') {
                    continue;
                }
                if (isset($validEnums[$col]) && ! in_array($val, $validEnums[$col], true)) {
                    continue;
                }
                $payload[$col] = $val;
            }

            $existing = Asset::withTrashed()->where('asset_tag', $payload['asset_tag'])->first();
            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Asset::create($payload);
                $created++;
            }
        }
        fclose($handle);

        $message = "Import complete: {$created} created, {$updated} updated, {$skipped} skipped.";
        if ($errors) {
            $message .= ' ' . implode(' ', array_slice($errors, 0, 5));
        }

        return back()->with($skipped > 0 && $created === 0 && $updated === 0 ? 'error' : 'success', $message);
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:assets,id',
            'action' => 'required|in:delete,restore,set_lifecycle_status',
            'lifecycle_status' => 'nullable|in:active,in_repair,retired,disposed',
        ]);

        $query = $data['action'] === 'restore' ? Asset::onlyTrashed() : Asset::query();
        $assets = $query->whereIn('id', $data['ids'])->get();

        foreach ($assets as $asset) {
            match ($data['action']) {
                'delete'  => tap($asset)->delete() && $this->logBulk($asset, $request->user()->id, 'deleted'),
                'restore' => tap($asset)->restore() && $this->logBulk($asset, $request->user()->id, 'restored'),
                'set_lifecycle_status' => $this->applyLifecycle($asset, $data['lifecycle_status'] ?? null, $request->user()->id),
            };
        }

        return back()->with('success', count($assets) . ' asset(s) updated.');
    }

    private function applyLifecycle(Asset $asset, ?string $status, int $userId): void
    {
        if (! $status) {
            return;
        }
        $asset->update(['lifecycle_status' => $status]);
        $this->logBulk($asset, $userId, 'updated', ['lifecycle_status']);
    }

    private function logBulk(Asset $asset, int $userId, string $action, ?array $changedFields = null): void
    {
        AssetActivityLog::create([
            'asset_id'       => $asset->id,
            'user_id'        => $userId,
            'action'         => $action,
            'changed_fields' => $changedFields,
        ]);
    }
}
