<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\CorrectLeaveRequest;
use App\Http\Requests\Leave\DecideLeaveRequest;
use App\Http\Requests\Leave\RejectLeaveRequest;
use App\Http\Resources\Leave\LeaveRequestResource;
use App\Models\Leave\LeaveRequest;
use App\Services\Leave\LeaveAuditor;
use App\Services\Leave\LeaveService;
use App\Support\Leave\LeaveAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveApprovalController extends Controller
{
    public function __construct(private readonly LeaveService $leave)
    {
    }

    // Pending requests this person may decide: direct reports' for approvers,
    // everyone's for admins, an empty list for everyone else.
    public function pending(Request $request): AnonymousResourceCollection
    {
        $requests = LeaveAccess::decidable($request->user())
            ->with('leaveType', 'user', 'approver')
            ->where('status', LeaveRequest::PENDING)
            ->orderBy('start_date')
            ->get();

        return LeaveRequestResource::collection($requests);
    }

    public function approve(DecideLeaveRequest $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $leave = $this->leave->approve($request->user(), $leaveRequest, $request->validated('comment'));

        return new LeaveRequestResource($leave->load('leaveType', 'user', 'approver'));
    }

    public function reject(RejectLeaveRequest $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $leave = $this->leave->reject($request->user(), $leaveRequest, $request->validated('comment'));

        return new LeaveRequestResource($leave->load('leaveType', 'user', 'approver'));
    }

    public function correct(CorrectLeaveRequest $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $data = $request->validated();
        $description = $this->leave->correct($request->user(), $leaveRequest, $data['status'], $data['reason'], $data['comment'] ?? null);
        LeaveAuditor::record($request, $description);

        return new LeaveRequestResource($leaveRequest->fresh()->load('leaveType', 'user', 'approver'));
    }
}
