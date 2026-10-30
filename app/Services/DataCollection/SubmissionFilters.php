<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

// The one set of filters behind the data table, summary, map, dashboard and
// exports, so every view of a form agrees on which submissions count:
//   from, to       collected_at date range (YYYY-MM-DD, inclusive)
//   enumerator     submitted_by user id
//   status         pending|approved|not_approved|on_hold
//   q              free text across answers, device id, client_uuid
//   answers        {field: value} — question equals value (for a
//                  select_multiple: includes it); repeat fields as "repeat/child"
//   deleted        "only" shows soft-deleted submissions
class SubmissionFilters
{
    public const KEYS = ['from', 'to', 'enumerator', 'status', 'q', 'answers', 'deleted'];

    public static function fromRequest(Request $request): array
    {
        $filters = [];
        foreach (self::KEYS as $key) {
            $value = $request->input($key);
            if ($value !== null && $value !== '' && $value !== []) {
                $filters[$key] = $value;
            }
        }
        if (isset($filters['answers'])) {
            $filters['answers'] = is_array($filters['answers'])
                ? array_filter($filters['answers'], fn ($v) => $v !== null && $v !== '' && is_scalar($v))
                : [];
        }

        return $filters;
    }

    // $hiddenFields: PII fields the viewer may not see. They can't be searched
    // or filtered on either, or a search for a name would reveal who's in the data.
    public static function query(DataCollectionForm $form, array $filters, array $hiddenFields = []): Builder
    {
        $filters['answers'] = array_diff_key($filters['answers'] ?? [], array_flip($hiddenFields));

        $q = DataCollectionSubmission::query()->where('data_collection_submissions.form_id', $form->id);

        if (($filters['deleted'] ?? null) === 'only') {
            $q->onlyTrashed();
        }
        if (! empty($filters['from'])) {
            $q->where('collected_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $q->where('collected_at', '<=', $filters['to'].' 23:59:59');
        }
        if (! empty($filters['enumerator'])) {
            $q->where('submitted_by', (int) $filters['enumerator']);
        }
        if (! empty($filters['status']) && in_array($filters['status'], DataCollectionSubmission::STATUSES, true)) {
            $q->where('status', $filters['status']);
        }

        foreach ($filters['answers'] ?? [] as $field => $value) {
            $q->whereIn('data_collection_submissions.id', DataCollectionSubmissionValue::query()
                ->select('submission_id')
                ->where('form_id', $form->id)
                ->where('field', (string) $field)
                ->where('value', (string) $value));
        }

        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr((string) $filters['q'], 0, 100)).'%';
            $q->where(function (Builder $w) use ($form, $term, $hiddenFields) {
                $w->where('client_uuid', 'like', $term)
                    ->orWhere('device_id', 'like', $term)
                    ->orWhereIn('data_collection_submissions.id', DataCollectionSubmissionValue::query()
                        ->select('submission_id')
                        ->where('form_id', $form->id)
                        ->when($hiddenFields, fn ($v) => $v->whereNotIn('field', $hiddenFields))
                        ->where('value', 'like', $term));
            });
        }

        return $q;
    }
}
