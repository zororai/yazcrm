<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly StockService $stock,
    ) {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;

        return Inertia::render('PurchaseOrders/Index', [
            'orders' => PurchaseOrder::with(['supplier:id,name', 'requestedBy:id,name'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->get(),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'stores'    => Store::orderBy('name')->get(['id', 'name']),
            'items'     => Item::where('is_active', true)->orderBy('name')->get(['id', 'item_code', 'name']),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'supplier_id'              => 'required|exists:suppliers,id',
            'store_id'                 => 'nullable|exists:stores,id',
            'order_date'               => 'nullable|date',
            'expected_delivery_date'   => 'nullable|date',
            'tax'                      => 'nullable|numeric|min:0',
            'notes'                    => 'nullable|string',
            'lines'                    => 'required|array|min:1',
            'lines.*.item_id'          => 'nullable|exists:items,id',
            'lines.*.description'      => 'nullable|string|max:255',
            'lines.*.quantity'         => 'required|integer|min:1',
            'lines.*.unit_cost'        => 'required|numeric|min:0',
        ]);

        $po = $this->service->create($request->user(), collect($data)->except('lines')->all(), $data['lines']);

        return redirect()->route('purchase-orders.show', $po)->with('success', 'Purchase order created.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        return Inertia::render('PurchaseOrders/Show', [
            'order' => $purchaseOrder->load(['supplier', 'store:id,name', 'requestedBy:id,name', 'approvedBy:id,name', 'items.item:id,name,item_code', 'receipts:id,receipt_number,purchase_order_id,created_at']),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->act(fn () => $this->service->submitForApproval($purchaseOrder, $request->user()), $request, 'Submitted for approval.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->act(fn () => $this->service->approve($purchaseOrder, $request->user()), $request, 'Purchase order approved.');
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        return $this->act(fn () => $this->service->reject($purchaseOrder, $request->user(), $data['reason']), $request, 'Purchase order rejected.');
    }

    public function markSent(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->act(fn () => $this->service->markSent($purchaseOrder, $request->user()), $request, 'Marked as sent to supplier.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }
        if (! $purchaseOrder->store_id) {
            return back()->with('error', 'This purchase order has no store to receive into.');
        }
        if (! in_array($purchaseOrder->status, ['sent', 'approved', 'partially_received'], true)) {
            return back()->with('error', "Cannot receive against a purchase order in status '{$purchaseOrder->status}'.");
        }

        $data = $request->validate([
            'lines'                         => 'required|array|min:1',
            'lines.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'lines.*.quantity'               => 'required|integer|min:1',
        ]);

        $poItems = $purchaseOrder->items()->get()->keyBy('id');
        $lines = [];
        foreach ($data['lines'] as $line) {
            $poItem = $poItems->get($line['purchase_order_item_id']);
            if (! $poItem || ! $poItem->item_id) {
                continue;
            }
            $lines[] = [
                'item_id'                 => $poItem->item_id,
                'purchase_order_item_id'  => $poItem->id,
                'quantity'                => $line['quantity'],
                'unit_cost'               => $poItem->unit_cost,
            ];
        }

        $this->stock->receiveStock($purchaseOrder->store, $request->user(), $lines, [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id'       => $purchaseOrder->supplier_id,
            'supplier_name'     => $purchaseOrder->supplier?->name,
            'reference_number'  => $purchaseOrder->po_number,
        ]);

        return back()->with('success', 'Goods received against this purchase order.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        return $this->act(fn () => $this->service->cancel($purchaseOrder, $request->user(), $data['reason']), $request, 'Purchase order cancelled.');
    }

    private function act(callable $action, Request $request, string $message): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $orders = PurchaseOrder::with(['supplier:id,name', 'requestedBy:id,name'])->latest()->get();

        return ResponseFacade::streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['PO Number', 'Supplier', 'Status', 'Order Date', 'Requested By', 'Subtotal', 'Tax', 'Total']);
            foreach ($orders as $po) {
                fputcsv($out, [
                    $po->po_number, $po->supplier?->name, $po->status,
                    $po->order_date?->toDateString(), $po->requestedBy?->name,
                    $po->subtotal, $po->tax, $po->total,
                ]);
            }
            fclose($out);
        }, 'purchase-orders.csv', ['Content-Type' => 'text/csv']);
    }
}
