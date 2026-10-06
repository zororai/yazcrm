<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Item;
use App\Models\ProcurementRequisition;
use App\Models\User;
use App\Services\ProcurementRequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProcurementRequisitionController extends Controller
{
    public function __construct(private readonly ProcurementRequisitionService $service)
    {
    }

    // Stage 2 — Head of Programs review. Mapped to the closest existing role
    // ("programs"), plus admin/director as standing overrides.
    private function isReviewer(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'programs'], true);
    }

    // Stage 3 — Executive Director approval. Mapped to the "director" role
    // (closest existing equivalent), plus admin as a standing override.
    private function isApprover(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;
        $user = $request->user();

        $query = ProcurementRequisition::with(['requestedBy:id,name', 'department:id,name'])->latest();

        // Everyone sees their own requisitions; reviewers/approvers/admin see all.
        if (! $this->isReviewer($user) && ! $this->isApprover($user)) {
            $query->where('requested_by', $user->id);
        }

        return Inertia::render('Procurement/Requisitions/Index', [
            'requisitions' => $query->when($status, fn ($q) => $q->where('status', $status))->get(),
            'departments'  => Department::orderBy('name')->get(['id', 'name']),
            'items'        => Item::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'isReviewer'   => $this->isReviewer($user),
            'isApprover'   => $this->isApprover($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'department_id'      => 'nullable|exists:departments,id',
            'title'              => 'required|string|max:255',
            'justification'      => 'nullable|string',
            'currency'           => 'nullable|string|max:10',
            'required_by_date'   => 'nullable|date',
            'lines'              => 'required|array|min:1',
            'lines.*.item_id'             => 'nullable|exists:items,id',
            'lines.*.description'         => 'nullable|required_without:lines.*.item_id|string|max:255',
            'lines.*.quantity'            => 'required|integer|min:1',
            'lines.*.estimated_unit_cost' => 'required|numeric|min:0',
        ]);

        $requisition = $this->service->create($request->user(), collect($data)->except('lines')->all(), $data['lines']);

        return redirect()->route('procurement-requisitions.show', $requisition)->with('success', 'Requisition created.');
    }

    public function show(Request $request, ProcurementRequisition $procurementRequisition): Response
    {
        $user = $request->user();

        if ($procurementRequisition->requested_by !== $user->id && ! $this->isReviewer($user) && ! $this->isApprover($user)) {
            abort(403);
        }

        return Inertia::render('Procurement/Requisitions/Show', [
            'requisition' => $procurementRequisition->load([
                'requestedBy:id,name', 'department:id,name', 'reviewedBy:id,name', 'approvedBy:id,name',
                'items.item:id,name', 'activityLogs.user:id,name',
            ]),
            'signatures'  => $this->signatures($procurementRequisition),
            'isReviewer'  => $this->isReviewer($user),
            'isApprover'  => $this->isApprover($user),
            'isOwner'     => $procurementRequisition->requested_by === $user->id,
        ]);
    }

    // Electronic sign-off block (Prepared / Reviewed / Authorized / Paid).
    // Each row is signed by the person who performed that step, at the time
    // they performed it — taken from the activity log and, for "Paid by",
    // the Finance Officer who recorded the linked payment requisition.
    private function signatures(ProcurementRequisition $requisition): array
    {
        $logged = function (string $action) use ($requisition) {
            $log = $requisition->activityLogs->firstWhere('action', $action);

            return ['name' => $log?->user?->name, 'signed_at' => $log?->created_at];
        };

        $payment = $requisition->purchaseOrder?->paymentRequisitions()
            ->where('status', 'recorded')
            ->with('recordedBy:id,name')
            ->latest('recorded_at')
            ->first();

        return [
            ['label' => 'Prepared by']   + $logged('requisition_submitted'),
            ['label' => 'Reviewed by']   + $logged('requisition_reviewed'),
            ['label' => 'Authorized by'] + $logged('requisition_approved'),
            ['label' => 'Paid by', 'name' => $payment?->recordedBy?->name, 'signed_at' => $payment?->recorded_at],
        ];
    }

    public function submit(Request $request, ProcurementRequisition $procurementRequisition): RedirectResponse
    {
        if ($procurementRequisition->requested_by !== $request->user()->id) {
            abort(403);
        }

        return $this->act(fn () => $this->service->submitForReview($procurementRequisition, $request->user()), 'Submitted for review.');
    }

    public function review(Request $request, ProcurementRequisition $procurementRequisition): RedirectResponse
    {
        if (! $this->isReviewer($request->user())) {
            abort(403);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        return $this->act(fn () => $this->service->review($procurementRequisition, $request->user(), $data['notes'] ?? null), 'Requisition reviewed — sent for approval.');
    }

    public function rejectAtReview(Request $request, ProcurementRequisition $procurementRequisition): RedirectResponse
    {
        if (! $this->isReviewer($request->user())) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->act(fn () => $this->service->rejectAtReview($procurementRequisition, $request->user(), $data['reason']), 'Requisition rejected.');
    }

    public function approve(Request $request, ProcurementRequisition $procurementRequisition): RedirectResponse
    {
        if (! $this->isApprover($request->user())) {
            abort(403);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:2000']);

        return $this->act(fn () => $this->service->approve($procurementRequisition, $request->user(), $data['notes'] ?? null), 'Requisition approved.');
    }

    public function rejectAtApproval(Request $request, ProcurementRequisition $procurementRequisition): RedirectResponse
    {
        if (! $this->isApprover($request->user())) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->act(fn () => $this->service->rejectAtApproval($procurementRequisition, $request->user(), $data['reason']), 'Requisition rejected.');
    }

    public function cancel(Request $request, ProcurementRequisition $procurementRequisition): RedirectResponse
    {
        if ($procurementRequisition->requested_by !== $request->user()->id && ! $this->isApprover($request->user())) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->act(fn () => $this->service->cancel($procurementRequisition, $request->user(), $data['reason']), 'Requisition cancelled.');
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
