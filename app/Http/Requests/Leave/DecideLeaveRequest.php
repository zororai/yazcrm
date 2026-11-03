<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// Approve. The policy runs before validation so someone who may not decide
// gets the 403 explaining why, not a validation error.
class DecideLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('decide', $this->route('leaveRequest'));

        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
