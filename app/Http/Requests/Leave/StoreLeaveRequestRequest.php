<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

// Shape only. The rules that need the database (working days, overlap,
// balance, attachment required by the type) are in LeaveService::submit.
class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id,is_active,1'],
            'start_date'    => ['required', 'date_format:Y-m-d'],
            'end_date'      => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason'        => ['nullable', 'string', 'max:2000'],
            'attachment'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.exists'    => 'Pick one of the leave types on the list.',
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
            'attachment.mimes'        => 'The attachment must be a PDF or a photo (JPG or PNG).',
            'attachment.max'          => 'The attachment must be 5 MB or smaller.',
        ];
    }
}
