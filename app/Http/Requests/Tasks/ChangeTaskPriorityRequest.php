<?php

namespace App\Http\Requests\Tasks;

use App\Support\Tasks\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTaskPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changePriority', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::in(TaskPriority::ALL)],
        ];
    }
}
