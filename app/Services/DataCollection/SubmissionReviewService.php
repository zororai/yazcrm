<?php

namespace App\Services\DataCollection;

use App\Jobs\DataCollection\ProcessSubmission;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Changes made to submissions in the CRM. Every one is audited with the
// old and new values; deleting is always soft.
class SubmissionReviewService
{
    public function __construct(private readonly SubmissionValidator $validator)
    {
    }

    public function setStatus(DataCollectionSubmission $submission, User $actor, string $status, ?string $note): void
    {
        if ($submission->status === $status && ($note === null || $note === $submission->status_note)) {
            return;
        }

        DB::transaction(function () use ($submission, $actor, $status, $note) {
            $old = ['status' => $submission->status, 'status_note' => $submission->status_note];

            $submission->update([
                'status'      => $status,
                'status_note' => $note,
                'status_by'   => $actor->id,
                'status_at'   => now(),
            ]);

            DataCollectionSubmissionAudit::create([
                'submission_id' => $submission->id,
                'user_id'       => $actor->id,
                'action'        => 'status_changed',
                'old'           => $old,
                'new'           => ['status' => $status, 'status_note' => $note],
                'note'          => $note,
            ]);
        });
    }

    // Replaces answers with $changes (field => value; null clears it),
    // validated against the submission's own form version. Returns the
    // validator's field errors, or [] once saved. Only changed fields are
    // written to the audit trail.
    public function edit(DataCollectionSubmission $submission, User $actor, array $changes, ?string $note): array
    {
        $data = $submission->data ?? [];
        $old = [];
        $new = [];

        foreach ($changes as $field => $value) {
            $before = $data[$field] ?? null;
            if ($before === $value) {
                continue;
            }
            $old[$field] = $before;
            $new[$field] = $value;
            if ($value === null || $value === '') {
                unset($data[$field]);
            } else {
                $data[$field] = $value;
            }
        }

        if (! $new) {
            return [];
        }

        $media = [];
        foreach ($submission->media as $m) {
            $media[$m->field_name][] = $m->original_name;
        }
        // Photos the device listed but hasn't uploaded yet still count.
        foreach ($submission->formVersion?->schema ? \App\Support\DataCollection\SchemaWalker::fields($submission->formVersion->schema) : [] as $field) {
            if ($field['type'] === 'photo' && $field['repeat'] === null && is_string($data[$field['name']] ?? null)) {
                $media[$field['name']][] = $data[$field['name']];
            }
        }

        $check = $this->validator->validate($submission->formVersion?->schema ?? [], $data, $media);
        if ($check['errors']) {
            return $check['errors'];
        }

        DB::transaction(function () use ($submission, $actor, $data, $old, $new, $note, $check) {
            $submission->update(['data' => $data, 'completion' => $check['completion']]);

            DataCollectionSubmissionAudit::create([
                'submission_id' => $submission->id,
                'user_id'       => $actor->id,
                'action'        => 'edited',
                'old'           => $old,
                'new'           => $new,
                'note'          => $note,
            ]);
        });

        ProcessSubmission::dispatch($submission->id);

        return [];
    }

    public function delete(DataCollectionSubmission $submission, User $actor, ?string $reason): void
    {
        DB::transaction(function () use ($submission, $actor, $reason) {
            $submission->delete();
            DataCollectionSubmissionAudit::create([
                'submission_id' => $submission->id,
                'user_id'       => $actor->id,
                'action'        => 'deleted',
                'note'          => $reason,
            ]);
        });
    }

    public function restore(DataCollectionSubmission $submission, User $actor): void
    {
        DB::transaction(function () use ($submission, $actor) {
            $submission->restore();
            DataCollectionSubmissionAudit::create([
                'submission_id' => $submission->id,
                'user_id'       => $actor->id,
                'action'        => 'restored',
            ]);
        });
    }
}
