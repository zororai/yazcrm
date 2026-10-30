<?php

namespace App\Http\Requests\DataCollection;

use Illuminate\Foundation\Http\FormRequest;

// The envelope only. A malformed envelope is the one 422 the batch endpoint
// gives; problems inside an item are that item's "rejected" result.
class SubmitBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submissions' => ['present', 'array', 'list', 'max:'.config('data_collection.batch_max')],
        ];
    }

    public function messages(): array
    {
        return [
            'submissions.list' => 'submissions must be a JSON array.',
            'submissions.max'  => 'Send at most :max submissions per batch.',
        ];
    }
}
