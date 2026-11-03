<?php

namespace App\Http\Requests\Leave;

use App\Models\Leave\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CorrectLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('correct', $this->route('leaveRequest'));

        return true;
    }

    public function rules(): array
    {
        return [
            'status'  => ['required', Rule::in([LeaveRequest::APPROVED, LeaveRequest::REJECTED, LeaveRequest::CANCELLED])],
            'reason'  => ['required', 'string', 'min:5', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Give the reason for correcting this decision. It is kept in the audit trail.',
            'reason.min'      => 'Give the reason for correcting this decision. It is kept in the audit trail.',
        ];
    }
}
