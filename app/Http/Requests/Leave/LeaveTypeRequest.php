<?php

namespace App\Http\Requests\Leave;

use Illuminate\Validation\Rule;

class LeaveTypeRequest extends LeaveAdminRequest
{
    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:100', Rule::unique('leave_types', 'name')->ignore($this->route('leaveType'))],
            'default_days'        => ['required', 'numeric', 'min:0', 'max:366'],
            'requires_attachment' => ['boolean'],
            'is_paid'             => ['boolean'],
            'is_active'           => ['boolean'],
        ];
    }
}
