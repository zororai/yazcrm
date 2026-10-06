<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Item;
use App\Models\StockIssue;
use App\Models\StockIssueItem;
use App\Models\StockReceiptItem;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

// Inventory dashboard — stock on hand, items issued/received over a period,
// and items at or below their reorder level. Quantities only: items carry no
// unit cost, so there is no stock valuation here.
class InventoryDashboardController extends Controller
{
    private const PERIODS = ['30d' => 30, '90d' => 90, '12m' => 365];

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/Dashboard', [
            ...$this->data($request),
            'stores'      => Store::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters'     => [
                'period'        => $this->period($request),
                'store_id'      => $request->input('store_id', ''),
                'department_id' => $request->input('department_id', ''),
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.inventory-dashboard-pdf', [
            ...$this->data($request),
            'generatedAt'    => now(),
            'storeName'      => $request->filled('store_id') ? Store::find($request->integer('store_id'))?->name : null,
            'departmentName' => $request->filled('department_id') ? Department::find($request->integer('department_id'))?->name : null,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('inventory-dashboard-' . now()->format('Y-m-d') . '.pdf');
    }

    private function period(Request $request): string
    {
        $period = $request->string('period')->toString();

        return array_key_exists($period, self::PERIODS) ? $period : '90d';
    }

    private function data(Request $request): array
    {
        $period  = $this->period($request);
        $storeId = $request->filled('store_id') ? $request->integer('store_id') : null;
        $deptId  = $request->filled('department_id') ? $request->integer('department_id') : null;
        $to      = CarbonImmutable::now();
        $from    = $to->subDays(self::PERIODS[$period])->startOfDay();

        // ── Movements in the period ──────────────────────────────────────────
        $issued = StockIssueItem::query()
            ->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_items.stock_issue_id')
            ->leftJoin('departments', 'departments.id', '=', 'stock_issues.department_id')
            ->whereBetween('stock_issues.created_at', [$from, $to])
            ->when($storeId, fn ($q) => $q->where('stock_issues.store_id', $storeId))
            ->when($deptId, fn ($q) => $q->where('stock_issues.department_id', $deptId))
            ->get([
                'stock_issue_items.item_id', 'stock_issue_items.quantity',
                'stock_issues.id as issue_id', 'stock_issues.created_at as moved_at',
                'departments.name as department',
            ]);

        // Receipts have no department, so a department filter leaves them out.
        $received = $deptId ? collect() : StockReceiptItem::query()
            ->join('stock_receipts', 'stock_receipts.id', '=', 'stock_receipt_items.stock_receipt_id')
            ->whereBetween('stock_receipts.created_at', [$from, $to])
            ->when($storeId, fn ($q) => $q->where('stock_receipts.store_id', $storeId))
            ->get(['stock_receipt_items.quantity', 'stock_receipts.created_at as moved_at']);

        $itemNames = Item::whereIn('id', $issued->pluck('item_id')->unique())->pluck('name', 'id');

        $topItems = $issued->groupBy('item_id')
            ->map(fn ($rows, $itemId) => [
                'name'     => $itemNames[$itemId] ?? "Item #{$itemId}",
                'quantity' => (int) $rows->sum('quantity'),
                'issues'   => $rows->pluck('issue_id')->unique()->count(),
            ])
            ->sortByDesc('quantity')->take(10)->values();

        $byDepartment = $issued->groupBy(fn ($r) => $r->department ?? 'No department')
            ->map(fn ($rows, $name) => [
                'name'     => $name,
                'quantity' => (int) $rows->sum('quantity'),
                'issues'   => $rows->pluck('issue_id')->unique()->count(),
            ])
            ->sortByDesc('quantity')->values();

        // ── Stock on hand & reorder list ─────────────────────────────────────
        $stockRows = StoreStock::query()
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->get(['store_id', 'item_id', 'quantity', 'reserved_quantity']);
        $stockByItem = $stockRows->groupBy('item_id');

        $items = Item::with('category:id,name')
            ->where('is_active', true)
            ->when($storeId, fn ($q) => $q->where(fn ($q) => $q
                ->where('default_store_id', $storeId)
                ->orWhereIn('id', $stockRows->pluck('item_id'))))
            ->get(['id', 'name', 'category_id', 'unit_of_measure', 'reorder_level', 'minimum_stock']);

        // Flag an item once it is at/below its reorder level, or out of stock
        // when it is tracked here (has a reorder level or a stock record).
        $reorder = $items->map(function (Item $item) use ($stockByItem) {
            $rows      = $stockByItem->get($item->id, collect());
            $available = (int) $rows->sum(fn ($r) => $r->quantity - $r->reserved_quantity);
            $tracked   = $item->reorder_level > 0 || $rows->isNotEmpty();

            $status = match (true) {
                $tracked && $available <= 0                                    => 'out',
                $item->reorder_level > 0 && $available <= $item->reorder_level => 'low',
                default                                                        => null,
            };

            return $status ? [
                'id'            => $item->id,
                'name'          => $item->name,
                'category'      => $item->category?->name,
                'unit'          => $item->unit_of_measure,
                'available'     => $available,
                'reorder_level' => (int) $item->reorder_level,
                'minimum_stock' => (int) $item->minimum_stock,
                'status'        => $status,
            ] : null;
        })->filter()
            ->sortBy([['status', 'desc'], ['available', 'asc']]) // 'out' before 'low'
            ->values();

        $storeNames = Store::pluck('name', 'id');
        $byStore = $stockRows->groupBy('store_id')
            ->map(fn ($rows, $id) => [
                'name'     => $storeNames[$id] ?? "Store #{$id}",
                'items'    => $rows->where('quantity', '>', 0)->count(),
                'quantity' => (int) $rows->sum('quantity'),
                'reserved' => (int) $rows->sum('reserved_quantity'),
            ])
            ->sortByDesc('quantity')->values();

        $recentIssues = StockIssue::with(['store:id,name', 'department:id,name', 'issuedBy:id,name'])
            ->withSum('items as units', 'quantity')
            ->withCount('items')
            ->whereBetween('created_at', [$from, $to])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->latest()->take(10)->get()
            ->map(fn (StockIssue $i) => [
                'id'         => $i->id,
                'number'     => $i->issue_number,
                'date'       => $i->created_at?->toDateString(),
                'store'      => $i->store?->name,
                'department' => $i->department?->name,
                'issued_to'  => $i->issued_to,
                'issued_by'  => $i->issuedBy?->name,
                'lines'      => (int) $i->items_count,
                'units'      => (int) $i->units,
            ]);

        return [
            'period'  => ['key' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'active_items'     => $items->count(),
                'units_on_hand'    => (int) $stockRows->sum('quantity'),
                'units_reserved'   => (int) $stockRows->sum('reserved_quantity'),
                'issues'           => $issued->pluck('issue_id')->unique()->count(),
                'units_issued'     => (int) $issued->sum('quantity'),
                'units_received'   => (int) $received->sum('quantity'),
                'receipts_counted' => ! $deptId,
                'low_stock'        => $reorder->where('status', 'low')->count(),
                'out_of_stock'     => $reorder->where('status', 'out')->count(),
                'in_transit'       => StockTransfer::where('status', 'dispatched')
                    ->when($storeId, fn ($q) => $q->where(fn ($q) => $q->where('from_store_id', $storeId)->orWhere('to_store_id', $storeId)))
                    ->count(),
            ],
            'trend'        => $this->trend($issued, $received, $from, $to, $period === '12m' ? 'month' : 'week'),
            'topItems'     => $topItems,
            'byDepartment' => $byDepartment,
            'byStore'      => $byStore,
            'reorder'      => $reorder->take(25)->values(),
            'reorderTotal' => $reorder->count(),
            'recentIssues' => $recentIssues,
        ];
    }

    // Units issued and received per week (or month), oldest first, with empty
    // buckets kept so the line doesn't skip gaps.
    private function trend(Collection $issued, Collection $received, CarbonImmutable $from, CarbonImmutable $to, string $unit): array
    {
        $key = fn ($date) => $unit === 'month'
            ? CarbonImmutable::parse($date)->format('Y-m')
            : CarbonImmutable::parse($date)->startOfWeek()->toDateString();

        $issuedBy   = $issued->groupBy(fn ($r) => $key($r->moved_at))->map->sum('quantity');
        $receivedBy = $received->groupBy(fn ($r) => $key($r->moved_at))->map->sum('quantity');

        $points = [];
        $cursor = $unit === 'month' ? $from->startOfMonth() : $from->startOfWeek();
        while ($cursor <= $to) {
            $k = $key($cursor);
            $points[] = [
                'label'    => $unit === 'month' ? $cursor->format('M Y') : $cursor->format('d M'),
                'issued'   => (int) ($issuedBy[$k] ?? 0),
                'received' => (int) ($receivedBy[$k] ?? 0),
            ];
            $cursor = $unit === 'month' ? $cursor->addMonth() : $cursor->addWeek();
        }

        return $points;
    }
}
