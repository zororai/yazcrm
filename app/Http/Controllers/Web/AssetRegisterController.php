<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class AssetRegisterController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $query = Asset::query();

        if ($request->filled('type'))             { $query->where('type', $request->type); }
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

        $assets = $query->latest()->paginate(25)->withQueryString();

        $summary = [
            'total'         => Asset::count(),
            'book_value'    => Asset::sum('cost'),
            'by_type'       => Asset::selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type'),
            'warranty_soon' => Asset::warrantyExpiring()->count(),
            'refresh_soon'  => Asset::dueRefresh()->count(),
            'in_repair'     => Asset::where('lifecycle_status', 'in_repair')->count(),
        ];

        return Inertia::render('Registry/Index', [
            'assets'  => $assets,
            'summary' => $summary,
            'filters' => $request->only(['type', 'lifecycle_status', 'data_sensitivity', 'criticality_393', 'search']),
        ]);
    }

    public function export(): Response
    {
        $assets = Asset::orderBy('asset_tag')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="asset-register-' . now()->format('Y-m-d') . '.csv"',
        ];

        $columns = [
            'asset_tag', 'name', 'type', 'location', 'ip_address', 'owner', 'os_version',
            'patch_status', 'data_sensitivity', 'criticality_393', 'source',
            'serial_number', 'supplier', 'acquired_on', 'cost', 'warranty_expires_on',
            'lifecycle_status', 'replace_due_on', 'notes',
        ];

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
}
