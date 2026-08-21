<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionActivityLog;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormAssignment;
use App\Models\DataCollectionSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DataCollectionSubmissionService
{
    public function __construct(private readonly SubmissionValidationService $validator)
    {
    }

    public function assignForm(DataCollectionForm $form, User $actor, int $assignedToUserId, ?string $dueDate = null): DataCollectionFormAssignment
    {
        if (! $form->current_version_id) {
            throw new RuntimeException('This form has no published version to assign yet.');
        }

        return DB::transaction(function () use ($form, $actor, $assignedToUserId, $dueDate) {
            $assignment = DataCollectionFormAssignment::create([
                'form_id'         => $form->id,
                'form_version_id' => $form->current_version_id,
                'assigned_to'     => $assignedToUserId,
                'assigned_by'     => $actor->id,
                'due_date'        => $dueDate,
                'status'          => 'assigned',
            ]);

            $this->log($actor, 'assignment_created', formId: $form->id, formVersionId: $form->current_version_id);

            return $assignment;
        });
    }

    public function startSubmission(DataCollectionFormAssignment $assignment, User $actor): DataCollectionSubmission
    {
        if ($assignment->assigned_to !== $actor->id) {
            throw new RuntimeException('This assignment does not belong to you.');
        }

        return DB::transaction(function () use ($assignment, $actor) {
            $submission = DataCollectionSubmission::create([
                'submission_uid' => (string) Str::uuid(),
                'project_id'     => $assignment->form->project_id,
                'form_id'        => $assignment->form_id,
                'form_version_id' => $assignment->form_version_id,
                'assignment_id'  => $assignment->id,
                'submitted_by'   => $actor->id,
                'status'         => 'draft',
                'answers'        => [],
                'started_at'     => now(),
            ]);

            if ($assignment->status === 'assigned') {
                $assignment->update(['status' => 'in_progress']);
            }

            $this->log($actor, 'submission_started', formId: $assignment->form_id, formVersionId: $assignment->form_version_id, submissionId: $submission->id);

            return $submission;
        });
    }

    public function saveDraft(DataCollectionSubmission $submission, User $actor, array $answers): DataCollectionSubmission
    {
        if ($submission->status !== 'draft') {
            throw new RuntimeException('Only a draft submission can be edited.');
        }

        return DB::transaction(function () use ($submission, $actor, $answers) {
            $submission->update([
                'answers'               => $answers,
                'completion_percentage' => $this->completionPercentage($submission->formVersion->schema, $answers),
            ]);

            $this->log($actor, 'submission_saved', formId: $submission->form_id, formVersionId: $submission->form_version_id, submissionId: $submission->id);

            return $submission;
        });
    }

    public function submit(DataCollectionSubmission $submission, User $actor): DataCollectionSubmission
    {
        if ($submission->status !== 'draft') {
            throw new RuntimeException('This submission has already been submitted.');
        }

        $errors = $this->validator->validate($submission->formVersion->schema, $submission->answers ?? []);
        if ($errors) {
            throw new RuntimeException(implode(' ', array_values($errors)));
        }

        return DB::transaction(function () use ($submission, $actor) {
            $submission->update([
                'status'                 => 'submitted',
                'submitted_at'           => now(),
                'completion_percentage'  => 100,
            ]);

            if ($submission->assignment_id) {
                $submission->assignment->update(['status' => 'completed']);
            }

            $this->log($actor, 'submission_submitted', formId: $submission->form_id, formVersionId: $submission->form_version_id, submissionId: $submission->id);

            return $submission;
        });
    }

    private function completionPercentage(array $schema, array $answers): int
    {
        $questions = collect($schema['sections'] ?? [])->flatMap(fn ($s) => $s['questions'] ?? []);
        if ($questions->isEmpty()) {
            return 0;
        }

        $answered = $questions->filter(function ($q) use ($answers) {
            $v = $answers[$q['id']] ?? null;

            return $v !== null && $v !== '' && $v !== [];
        })->count();

        return (int) round(($answered / $questions->count()) * 100);
    }

    private function log(
        User $actor,
        string $action,
        ?int $formId = null,
        ?int $formVersionId = null,
        ?int $submissionId = null,
    ): void {
        DataCollectionActivityLog::create([
            'form_id'         => $formId,
            'form_version_id' => $formVersionId,
            'submission_id'   => $submissionId,
            'user_id'         => $actor->id,
            'action'          => $action,
        ]);
    }
}
