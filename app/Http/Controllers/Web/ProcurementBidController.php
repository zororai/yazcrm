<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProcurementBidProcess;
use App\Models\ProcurementRequisition;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProcurementBidService;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProcurementBidController extends Controller
{
    public function __construct(
        private readonly ProcurementBidService $service,
        private readonly PurchaseOrderService $poService,
    ) {
    }

    // Stage 4/5 — Procurement / Evaluation team. Mapped to the same group
    // that already manages Suppliers & Purchase Orders elsewhere in the app.
    private function isProcurementTeam(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'stores', 'accounting_dep'], true);
    }

    // Stage 6 — Head of Programs review.
    private function isHop(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'programs'], true);
    }

    // Stage 7 — Head of Finance review. Mapped to "accounting_dep".
    private function isHof(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'accounting_dep'], true);
    }

    // Stage 8 — Executive Director / Board approval.
    private function isApprover(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function show(Request $request, ProcurementRequisition $procurementRequisition): Response
    {
        $user = $request->user();

        $procurementRequisition->load([
            'bidProcess.quotes.supplier', 'bidProcess.activityLogs.user:id,name', 'bidProcess.recommendedSupplier',
            'requestedBy:id,name', 'purchaseOrder:id,requisition_id,po_number,status',
        ]);

        if ($procurementRequisition->requested_by !== $user->id && ! $this->isProcurementTeam($user) && ! $this->isHop($user) && ! $this->isHof($user)) {
            abort(403);
        }

        return Inertia::render('Procurement/VendorSelection/Show', [
            'requisition'         => $procurementRequisition,
            'suppliers'           => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'stores'              => Store::orderBy('name')->get(['id', 'name']),
            'isProcurementTeam'   => $this->isProcurementTeam($user),
            'isHop'               => $this->isHop($user),
            'isHof'               => $this->isHof($user),
            'isApprover'          => $this->isApprover($user),
        ]);
    }

    // Stage 9 — Head of Programs issues the Purchase Order / Contract once
    // the vendor is approved, pre-filled from the requisition's line items.
    public function issuePurchaseOrder(Request $request, ProcurementBidProcess $bidProcess): RedirectResponse
    {
        if (! $this->isHop($request->user())) {
            abort(403);
        }

        if ($bidProcess->status !== 'approved') {
            return back()->with('error', 'The vendor must be approved before a purchase order can be issued.');
        }

        if ($bidProcess->requisition->purchaseOrder()->exists()) {
            return back()->with('error', 'A purchase order has already been issued for this requisition.');
        }

        $data = $request->validate([
            'store_id'                 => 'nullable|exists:stores,id',
            'expected_delivery_date'   => 'nullable|date',
        ]);

        $requisition = $bidProcess->requisition()->with('items')->first();

        $lines = $requisition->items->map(fn ($item) => [
            'item_id'     => $item->item_id,
            'description' => $item->description,
            'quantity'    => $item->quantity,
            'unit_cost'   => $item->estimated_unit_cost,
        ])->all();

        $po = $this->poService->create($request->user(), [
            'supplier_id'             => $bidProcess->recommended_supplier_id,
            'store_id'                => $data['store_id'] ?? null,
            'department_id'           => $requisition->department_id,
            'requisition_id'          => $requisition->id,
            'bid_process_id'          => $bidProcess->id,
            'order_date'              => now()->toDateString(),
            'expected_delivery_date'  => $data['expected_delivery_date'] ?? null,
            'notes'                   => "Issued from {$requisition->requisition_number} / vendor selection.",
        ], $lines);

        return redirect()->route('purchase-orders.show', $po)->with('success', 'Purchase order issued.');
    }

    public function start(Request $request, ProcurementRequisition $procurementRequisition): RedirectResponse
    {
        if (! $this->isProcurementTeam($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'bid_method'                  => 'required|string|in:request_for_quotes,sole_source,public_invitation',
            'bid_reference'               => 'nullable|string|max:255',
            'quotes'                      => 'nullable|array',
            'quotes.*.supplier_id'        => 'required|exists:suppliers,id',
            'quotes.*.quoted_amount'      => 'nullable|numeric|min:0',
            'quotes.*.notes'              => 'nullable|string|max:1000',
        ]);

        try {
            $this->service->start($procurementRequisition, $request->user(), $data['bid_method'], $data['bid_reference'] ?? null, $data['quotes'] ?? []);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bid process started.');
    }

    public function evaluate(Request $request, ProcurementBidProcess $bidProcess): RedirectResponse
    {
        if (! $this->isProcurementTeam($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'recommended_supplier_id' => 'required|exists:suppliers,id',
            'notes'                   => 'required|string|max:2000',
        ]);

        return $this->act(fn () => $this->service->evaluate($bidProcess, $request->user(), (int) $data['recommended_supplier_id'], $data['notes']), 'Vendor recommendation recorded.');
    }

    public function hopReview(Request $request, ProcurementBidProcess $bidProcess): RedirectResponse
    {
        if (! $this->isHop($request->user())) {
            abort(403);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        return $this->act(fn () => $this->service->hopReview($bidProcess, $request->user(), $data['notes'] ?? null), 'Reviewed by Head of Programs.');
    }

    public function hofReview(Request $request, ProcurementBidProcess $bidProcess): RedirectResponse
    {
        if (! $this->isHof($request->user())) {
            abort(403);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        return $this->act(fn () => $this->service->hofReview($bidProcess, $request->user(), $data['notes'] ?? null), 'Reviewed by Head of Finance.');
    }

    public function approve(Request $request, ProcurementBidProcess $bidProcess): RedirectResponse
    {
        if (! $this->isApprover($request->user())) {
            abort(403);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        return $this->act(fn () => $this->service->approve($bidProcess, $request->user(), $data['notes'] ?? null), 'Vendor approved.');
    }

    public function reject(Request $request, ProcurementBidProcess $bidProcess): RedirectResponse
    {
        if (! $this->isProcurementTeam($request->user()) && ! $this->isHop($request->user()) && ! $this->isHof($request->user()) && ! $this->isApprover($request->user())) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->act(fn () => $this->service->reject($bidProcess, $request->user(), $data['reason']), 'Vendor selection rejected.');
    }

    public function cancel(Request $request, ProcurementBidProcess $bidProcess): RedirectResponse
    {
        if (! $this->isProcurementTeam($request->user())) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->act(fn () => $this->service->cancel($bidProcess, $request->user(), $data['reason']), 'Vendor selection cancelled.');
    }

    private function act(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
