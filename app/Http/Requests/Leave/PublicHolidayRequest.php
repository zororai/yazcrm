<?php

namespace App\Http\Requests\Leave;

use Illuminate\Validation\Rule;

class PublicHolidayRequest extends LeaveAdminRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('public_holidays', 'date')->ignore($this->route('publicHoliday'))],
        ];
    }

    public function messages(): array
    {
        return [
            'date.unique' => 'There is already a public holiday on that date.',
        ];
    }
}
