<?php

namespace App\Http\Requests\Leave;

// Sets one person's entitlement for a leave type and year (creating the
// balance row if needed). used_days is never set by hand: it only moves
// through approvals and corrections.
class LeaveEntitlementRequest extends LeaveAdminRequest
{
    public function rules(): array
    {
        return [
            'user_id'       => ['required', 'integer', 'exists:users,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'entitled_days' => ['required', 'numeric', 'min:0', 'max:366'],
        ];
    }
}
