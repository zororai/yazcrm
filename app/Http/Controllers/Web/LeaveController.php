<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\CorrectLeaveRequest;
use App\Http\Requests\Leave\DecideLeaveRequest;
use App\Http\Requests\Leave\LeaveEntitlementRequest;
use App\Http\Requests\Leave\LeaveTypeRequest;
use App\Http\Requests\Leave\PublicHolidayRequest;
use App\Http\Requests\Leave\RejectLeaveRequest;
use App\Http\Resources\Leave\LeaveRequestResource;
use App\Models\Leave\LeaveBalance;
use App\Models\Leave\LeaveRequest;
use App\Models\Leave\LeaveType;
use App\Models\Leave\PublicHoliday;
use App\Models\User;
use App\Services\Leave\LeaveAuditor;
use App\Services\Leave\LeaveReport;
use App\Services\Leave\LeaveService;
use App\Services\Leave\LeaveSummary;
use App\Support\Leave\LeaveAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// CRM leave pages. Requests is shared by approvers (their direct reports)
// and leave admins (everyone, plus corrections); the settings pages and
// reports are leave admins only. Rules and balances go through LeaveService,
// exactly as the app's API does.
class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $leave)
    {
    }

    // ── Requests ─────────────────────────────────────────────────────────────

    public function requests(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = LeaveAccess::isAdmin($user);
        abort_unless($isAdmin || LeaveAccess::isApprover($user), 403);

        $filters = $request->validate([
            'status'  => ['nullable', Rule::in(LeaveRequest::STATUSES)],
            'user_id' => ['nullable', 'integer'],
            'year'    => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $year = (int) ($filters['year'] ?? now()->year);

        // Admins see everyone's, including their own (read-only); approvers
        // see their direct reports'.
        $query = $isAdmin
            ? LeaveRequest::query()
            : LeaveAccess::decidable($user);

        $requests = $query->with('leaveType', 'user', 'approver', 'corrector')
            ->whereYear('start_date', $year)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->limit(500)
            ->get();

        $people = $isAdmin
            ? User::orderBy('name')->get(['id', 'name'])
            : User::where('supervisor_id', $user->id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Leave/Requests', [
            'requests' => $requests->map(fn ($r) => (new LeaveRequestResource($r))->toArray($request) + [
                'corrector' => $r->corrector ? ['id' => $r->corrector->id, 'name' => $r->corrector->name] : null,
                'can_correct' => $user->can('correct', $r),
            ]),
            'people'   => $people,
            'filters'  => ['status' => $filters['status'] ?? '', 'user_id' => $filters['user_id'] ?? '', 'year' => $year],
            'isAdmin'  => $isAdmin,
        ]);
    }

    public function approve(DecideLeaveRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->leave->approve($request->user(), $leaveRequest, $request->validated('comment'));

        return back()->with('success', 'Leave approved.');
    }

    public function reject(RejectLeaveRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->leave->reject($request->user(), $leaveRequest, $request->validated('comment'));

        return back()->with('success', 'Leave rejected.');
    }

    public function correct(CorrectLeaveRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $data = $request->validated();
        $description = $this->leave->correct($request->user(), $leaveRequest, $data['status'], $data['reason'], $data['comment'] ?? null);
        LeaveAuditor::record($request, $description);

        return back()->with('success', 'Decision corrected. The change is in the audit trail.');
    }

    public function attachment(LeaveRequest $leaveRequest): StreamedResponse
    {
        $this->authorize('view', $leaveRequest);
        abort_unless($leaveRequest->attachment_path && Storage::disk('local')->exists($leaveRequest->attachment_path), 404);

        return Storage::disk('local')->download($leaveRequest->attachment_path);
    }

    // ── Leave types ──────────────────────────────────────────────────────────

    public function types(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Leave/Types', [
            'types' => LeaveType::withCount('requests')->orderBy('name')->get(),
        ]);
    }

    public function storeType(LeaveTypeRequest $request): RedirectResponse
    {
        LeaveType::create($request->validated());

        return back()->with('success', 'Leave type added.');
    }

    public function updateType(LeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($request->validated());

        return back()->with('success', 'Leave type saved.');
    }

    public function destroyType(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->ensureAdmin($request);

        if ($leaveType->requests()->exists()) {
            $leaveType->update(['is_active' => false]);

            return back()->with('success', "{$leaveType->name} has requests, so it was switched off instead of deleted.");
        }

        $leaveType->delete();

        return back()->with('success', 'Leave type deleted.');
    }

    // ── Public holidays ──────────────────────────────────────────────────────

    public function holidays(Request $request): Response
    {
        $this->ensureAdmin($request);
        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->year);

        return Inertia::render('Leave/Holidays', [
            'holidays' => PublicHoliday::whereYear('date', $year)->orderBy('date')->get()
                ->map(fn ($h) => ['id' => $h->id, 'name' => $h->name, 'date' => $h->date->toDateString()]),
            'year'     => $year,
        ]);
    }

    public function storeHoliday(PublicHolidayRequest $request): RedirectResponse
    {
        PublicHoliday::create($request->validated());

        return back()->with('success', 'Public holiday added.');
    }

    public function updateHoliday(PublicHolidayRequest $request, PublicHoliday $publicHoliday): RedirectResponse
    {
        $publicHoliday->update($request->validated());

        return back()->with('success', 'Public holiday saved.');
    }

    public function destroyHoliday(Request $request, PublicHoliday $publicHoliday): RedirectResponse
    {
        $this->ensureAdmin($request);
        $publicHoliday->delete();

        return back()->with('success', 'Public holiday deleted.');
    }

    // ── Entitlements ─────────────────────────────────────────────────────────

    public function entitlements(Request $request): Response
    {
        $this->ensureAdmin($request);
        $filters = $request->validate([
            'year'    => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'user_id' => ['nullable', 'integer'],
        ]);
        $year = (int) ($filters['year'] ?? now()->year);

        $users = User::where('is_active', true)
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Leave/Entitlements', [
            'rows'    => LeaveSummary::build($users, $year)->values(),
            'people'  => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => ['year' => $year, 'user_id' => $filters['user_id'] ?? ''],
        ]);
    }

    public function storeEntitlement(LeaveEntitlementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        LeaveBalance::updateOrCreate(
            ['user_id' => $data['user_id'], 'leave_type_id' => $data['leave_type_id'], 'year' => $data['year']],
            ['entitled_days' => $data['entitled_days']],
        );

        return back()->with('success', 'Entitlement saved.');
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    public function reports(Request $request): Response
    {
        $this->ensureAdmin($request);
        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->year);

        return Inertia::render('Leave/Reports', ['report' => LeaveReport::data($year)]);
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $this->ensureAdmin($request);
        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->year);

        return LeaveReport::csv($year);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(LeaveAccess::isAdmin($request->user()), 403, 'Only leave admins can open this page.');
    }
}
