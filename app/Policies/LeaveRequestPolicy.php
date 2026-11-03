<?php

namespace App\Policies;

use App\Models\Leave\LeaveRequest;
use App\Models\User;
use App\Support\Leave\LeaveAccess;
use Illuminate\Auth\Access\Response;

// Every refusal carries a message, because the app shows it as-is.
class LeaveRequestPolicy
{
    public function view(User $user, LeaveRequest $leave): Response
    {
        return LeaveAccess::canView($user, $leave)
            ? Response::allow()
            : Response::deny('You can only see your own leave requests or those of people you approve leave for.');
    }

    public function cancel(User $user, LeaveRequest $leave): Response
    {
        if ((int) $leave->user_id !== (int) $user->id) {
            return Response::deny('Only the person who applied can cancel this request.');
        }

        return $leave->isPending()
            ? Response::allow()
            : Response::deny('Only a pending request can be cancelled.');
    }

    public function decide(User $user, LeaveRequest $leave): Response
    {
        if ((int) $leave->user_id === (int) $user->id) {
            return Response::deny('You cannot approve or reject your own leave request.');
        }

        if (! LeaveAccess::canDecide($user, $leave)) {
            return Response::deny('You can only approve or reject leave for people who report to you.');
        }

        return $leave->isPending()
            ? Response::allow()
            : Response::deny('This request has already been decided. A leave admin can correct it in the CRM.');
    }

    public function correct(User $user, LeaveRequest $leave): Response
    {
        if (! LeaveAccess::isAdmin($user)) {
            return Response::deny('Only leave admins can correct a decision.');
        }

        if ((int) $leave->user_id === (int) $user->id) {
            return Response::deny('You cannot correct a decision on your own leave request.');
        }

        return in_array($leave->status, [LeaveRequest::APPROVED, LeaveRequest::REJECTED], true)
            ? Response::allow()
            : Response::deny('Only an approved or rejected request can be corrected.');
    }
}
