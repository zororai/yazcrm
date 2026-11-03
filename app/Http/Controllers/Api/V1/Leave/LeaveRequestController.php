<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveRequestRequest;
use App\Http\Resources\Leave\LeaveRequestResource;
use App\Models\Leave\LeaveRequest;
use App\Services\Leave\LeaveService;
use App\Services\Leave\LeaveSummary;
use App\Services\Leave\WorkingDays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

// The signed-in person's own leave. Every list here is filtered to
// user_id = them; other people's requests are only reachable through
// show(), which the policy guards.
class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveService $leave)
    {
    }

    public function balances(Request $request): JsonResponse
    {
        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->year);

        return response()->json(['year' => $year, 'balances' => LeaveSummary::forUser($request->user(), $year)]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(LeaveRequest::STATUSES)],
            'year'   => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $requests = LeaveRequest::with('leaveType', 'approver', 'user')
            ->where('user_id', $request->user()->id)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['year'] ?? null, fn ($q, $y) => $q->whereYear('start_date', $y))
            ->orderByDesc('start_date')
            ->get();

        return LeaveRequestResource::collection($requests);
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $leave = $this->leave->submit($request->user(), $request->validated(), $request->file('attachment'));

        return (new LeaveRequestResource($leave->load('leaveType', 'user', 'approver')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('view', $leaveRequest);

        return new LeaveRequestResource($leaveRequest->load('leaveType', 'user', 'approver'));
    }

    public function attachment(LeaveRequest $leaveRequest): StreamedResponse
    {
        $this->authorize('view', $leaveRequest);
        abort_unless($leaveRequest->attachment_path && Storage::disk('local')->exists($leaveRequest->attachment_path), 404, 'This request has no attachment.');

        return Storage::disk('local')->download($leaveRequest->attachment_path);
    }

    public function cancel(LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('cancel', $leaveRequest);

        return new LeaveRequestResource($this->leave->cancel($leaveRequest)->load('leaveType', 'user', 'approver'));
    }

    // The apply screen's live day count; same rule the server applies.
    public function workingDays(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $dates = WorkingDays::dates($data['start_date'], $data['end_date']);

        return response()->json(['days' => count($dates), 'dates' => $dates]);
    }
}
