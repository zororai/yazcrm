<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProcurementPaymentRequisition;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ProcurementPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProcurementPaymentController extends Controller
{
    public function __construct(private readonly ProcurementPaymentService $service)
    {
    }

    // Stage 11 (review) & 12 (bank loading) — Head of Finance. Mapped to "accounting_dep".
    private function isHof(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'accounting_dep'], true);
    }

    // Stage 11 (approval) & 13 (authorize & release) — Executive Director / Board.
    private function isApprover(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    // Stage 14 — Finance Officer records the completed payment. Same closest
    // role as Head of Finance (accounting_dep) — distinguished by who's acting.
    private function isFinanceOfficer(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'accounting_dep'], true);
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;
        $user = $request->user();

        $query = ProcurementPaymentRequisition::with(['purchaseOrder:id,po_number,supplier_id', 'purchaseOrder.supplier:id,name', 'preparedBy:id,name'])->latest();

        if (! $this->isHof($user) && ! $this->isApprover($user)) {
            $query->where('prepared_by', $user->id);
        }

        return Inertia::render('Procurement/Payments/Index', [
            'payments'  => $query->when($status, fn ($q) => $q->where('status', $status))->get(),
            'isHof'     => $this->isHof($user),
            'isApprover' => $this->isApprover($user),
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();
        if ($purchaseOrder->requested_by !== $user->id && ! $this->isHof($user) && ! $this->isApprover($user)) {
            abort(403);
        }

        if ($purchaseOrder->status !== 'received') {
            return back()->with('error', 'Delivery must be confirmed before a payment requisition can be raised.');
        }

        $data = $request->validate([
            'payee_name'      => 'required|string|max:255',
            'amount'          => 'required|numeric|min:0.01',
            'currency'        => 'nullable|string|max:10',
            'payment_method'  => 'nullable|string|in:bank_transfer,cheque,mobile_money,cash',
            'description'     => 'nullable|string|max:2000',
        ]);

        $payment = $this->service->create($user, $purchaseOrder, $data);

        return redirect()->route('procurement-payments.show', $payment)->with('success', 'Payment requisition created.');
    }

    public function show(Request $request, ProcurementPaymentRequisition $procurementPayment): Response
    {
        $user = $request->user();

        if ($procurementPayment->prepared_by !== $user->id && ! $this->isHof($user) && ! $this->isApprover($user)) {
            abort(403);
        }

        return Inertia::render('Procurement/Payments/Show', [
            'payment' => $procurementPayment->load([
                'purchaseOrder:id,po_number,supplier_id,total', 'purchaseOrder.supplier:id,name', 'preparedBy:id,name',
                'reviewedBy:id,name', 'approvedBy:id,name', 'loadedBy:id,name', 'releasedBy:id,name', 'recordedBy:id,name',
                'activityLogs.user:id,name',
            ]),
            'isOwner'     => $procurementPayment->prepared_by === $user->id,
            'isHof'       => $this->isHof($user),
            'isApprover'  => $this->isApprover($user),
            'isFinance'   => $this->isFinanceOfficer($user),
        ]);
    }

    public function submit(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if ($procurementPayment->prepared_by !== $request->user()->id) {
            abort(403);
        }

        return $this->act(fn () => $this->service->submitForReview($procurementPayment, $request->user()), 'Submitted for review.');
    }

    public function review(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if (! $this->isHof($request->user())) {
            abort(403);
        }
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        return $this->act(fn () => $this->service->review($procurementPayment, $request->user(), $data['notes'] ?? null), 'Reviewed — sent for approval.');
    }

    public function rejectAtReview(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if (! $this->isHof($request->user())) {
            abort(403);
        }
        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->act(fn () => $this->service->rejectAtReview($procurementPayment, $request->user(), $data['reason']), 'Payment requisition rejected.');
    }

    public function approve(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if (! $this->isApprover($request->user())) {
            abort(403);
        }
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        return $this->act(fn () => $this->service->approve($procurementPayment, $request->user(), $data['notes'] ?? null), 'Payment approved.');
    }

    public function rejectAtApproval(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if (! $this->isApprover($request->user())) {
            abort(403);
        }
        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->act(fn () => $this->service->rejectAtApproval($procurementPayment, $request->user(), $data['reason']), 'Payment requisition rejected.');
    }

    public function loadToBank(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if (! $this->isHof($request->user())) {
            abort(403);
        }
        $data = $request->validate([
            'bank_reference' => 'required|string|max:255',
            'notes'          => 'nullable|string|max:2000',
        ]);

        return $this->act(fn () => $this->service->loadToBank($procurementPayment, $request->user(), $data['bank_reference'], $data['notes'] ?? null), 'Payment loaded to banking system.');
    }

    public function release(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if (! $this->isApprover($request->user())) {
            abort(403);
        }
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        return $this->act(fn () => $this->service->release($procurementPayment, $request->user(), $data['notes'] ?? null), 'Payment authorized and released.');
    }

    public function record(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if (! $this->isFinanceOfficer($request->user())) {
            abort(403);
        }
        $data = $request->validate([
            'recording_reference' => 'nullable|string|max:255',
            'notes'               => 'nullable|string|max:2000',
        ]);

        return $this->act(fn () => $this->service->record($procurementPayment, $request->user(), $data['recording_reference'] ?? null, $data['notes'] ?? null), 'Payment recorded.');
    }

    public function cancel(Request $request, ProcurementPaymentRequisition $procurementPayment): RedirectResponse
    {
        if ($procurementPayment->prepared_by !== $request->user()->id && ! $this->isApprover($request->user())) {
            abort(403);
        }
        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->act(fn () => $this->service->cancel($procurementPayment, $request->user(), $data['reason']), 'Payment requisition cancelled.');
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
