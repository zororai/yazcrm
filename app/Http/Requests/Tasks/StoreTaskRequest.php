<?php

namespace App\Http\Requests\Tasks;

use App\Support\Tasks\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Task::class);
    }

    public function rules(): array
    {
        return [
            'board_id'    => 'required|exists:boards,id',
            'group_id'    => [
                'nullable',
                Rule::exists('task_groups', 'id')->where('board_id', $this->input('board_id')),
            ],
            'parent_id'   => [
                'nullable',
                Rule::exists('tasks', 'id')->where('board_id', $this->input('board_id')),
            ],
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => ['nullable', Rule::in(TaskPriority::ALL)],
            'start_date'  => 'nullable|date',
            'due_date'    => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
