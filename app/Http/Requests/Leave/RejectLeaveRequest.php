<?php

namespace App\Http\Requests\Leave;

class RejectLeaveRequest extends DecideLeaveRequest
{
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'Say why you are rejecting this request.',
        ];
    }
}
