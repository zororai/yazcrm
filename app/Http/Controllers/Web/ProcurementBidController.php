<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProcurementBidProcess;
use App\Models\ProcurementRequisition;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProcurementBidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProcurementBidController extends Controller
{
    public function __construct(private readonly ProcurementBidService $service)
    {
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

        $procurementRequisition->load(['bidProcess.quotes.supplier', 'bidProcess.activityLogs.user:id,name', 'bidProcess.recommendedSupplier', 'requestedBy:id,name']);

        if ($procurementRequisition->requested_by !== $user->id && ! $this->isProcurementTeam($user) && ! $this->isHop($user) && ! $this->isHof($user)) {
            abort(403);
        }

        return Inertia::render('Procurement/VendorSelection/Show', [
            'requisition'         => $procurementRequisition,
            'suppliers'           => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'isProcurementTeam'   => $this->isProcurementTeam($user),
            'isHop'               => $this->isHop($user),
            'isHof'               => $this->isHof($user),
            'isApprover'          => $this->isApprover($user),
        ]);
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
