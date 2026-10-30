<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionMedia;
use App\Services\DataCollection\FormFieldCatalog;
use App\Services\DataCollection\SubmissionReviewService;
use App\Support\DataCollection\FormAccess;
use App\Support\DataCollection\SchemaWalker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

// One submission: its answers (labels, PII masked as needed), photos,
// validation status, edits and the full audit trail.
class DataCollectionSubmissionController extends Controller
{
    public function __construct(private readonly SubmissionReviewService $review)
    {
    }

    public function show(Request $request, int $submission): Response
    {
        $submission = DataCollectionSubmission::withTrashed()
            ->with(['form.project:id,name', 'formVersion', 'submittedBy:id,name', 'statusBy:id,name', 'media', 'audits.user:id,name'])
            ->findOrFail($submission);
        $this->authorize('view', $submission);

        $user = $request->user();
        $catalog = FormFieldCatalog::for($submission->form);
        $hidden = $catalog->hiddenFor(FormAccess::canSeePii($user));
        $schema = $submission->formVersion?->schema ?? [];

        // The audit trail must not leak hidden answers either.
        $maskAudit = fn (?array $values) => $values === null ? null : $catalog->mask($values, $hidden);

        return Inertia::render('DataCollection/Submissions/Show', [
            'submission' => [
                'id'           => $submission->id,
                'client_uuid'  => $submission->client_uuid,
                'form'         => $submission->form->only(['id', 'name']) + ['project' => $submission->form->project?->only(['id', 'name'])],
                'version'      => $submission->formVersion?->version_number,
                'submitted_by' => $submission->submittedBy?->name,
                'collected_at' => $submission->collected_at,
                'started_at'   => $submission->started_at,
                'received_at'  => $submission->received_at,
                'device_id'    => $submission->device_id,
                'app_version'  => $submission->app_version,
                'lat'          => $submission->lat,
                'lng'          => $submission->lng,
                'completion'   => $submission->completion,
                'status'       => $submission->status,
                'status_note'  => $submission->status_note,
                'status_by'    => $submission->statusBy?->name,
                'status_at'    => $submission->status_at,
                'crm_record'   => $submission->crm_record_id ? ['type' => $submission->crm_record_type, 'id' => $submission->crm_record_id] : null,
                'deleted_at'   => $submission->deleted_at,
                'data'         => $catalog->mask($submission->data ?? [], $hidden),
            ],
            'schema'     => ['children' => $schema['children'] ?? [], 'choices' => $schema['choices'] ?? []],
            'hidden'     => $hidden,
            'media'      => $submission->media->map(fn (DataCollectionSubmissionMedia $m) => [
                'id'         => $m->id,
                'field_name' => $m->field_name,
                'filename'   => $m->original_name,
                'size'       => $m->size,
                'url'        => in_array($m->field_name, $hidden, true) ? null : route('data-collection.media.show', $m),
            ]),
            'audits'     => $submission->audits->map(fn ($a) => [
                'id'         => $a->id,
                'action'     => $a->action,
                'user'       => $a->user?->name ?? 'System',
                'old'        => $maskAudit($a->old),
                'new'        => $maskAudit($a->new),
                'note'       => $a->note,
                'created_at' => $a->created_at,
            ]),
            'statuses'   => DataCollectionSubmission::STATUSES,
            'can'        => [
                'edit'    => $request->user()->can('update', $submission) && ! $submission->trashed(),
                'delete'  => $request->user()->can('delete', $submission),
                'pii'     => $hidden === [],
            ],
            'editable'   => $this->editableFields($schema, $hidden),
        ]);
    }

    public function status(Request $request, DataCollectionSubmission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);

        $data = $request->validate([
            'status' => 'required|in:'.implode(',', DataCollectionSubmission::STATUSES),
            'note'   => 'nullable|string|max:1000',
        ]);

        $this->review->setStatus($submission, $request->user(), $data['status'], $data['note'] ?? null);

        return back()->with('success', 'Validation status saved.');
    }

    public function update(Request $request, DataCollectionSubmission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);

        $data = $request->validate([
            'changes' => 'required|array',
            'note'    => 'nullable|string|max:1000',
        ]);

        // Only top-level, non-hidden answer fields can be changed here.
        $allowed = array_column($this->editableFields($submission->formVersion?->schema ?? [],
            FormFieldCatalog::for($submission->form)->hiddenFor(FormAccess::canSeePii($request->user()))), 'name');
        $changes = array_intersect_key($data['changes'], array_flip($allowed));

        $errors = $this->review->edit($submission, $request->user(), $changes, $data['note'] ?? null);
        if ($errors) {
            return back()->withErrors(collect($errors)->mapWithKeys(fn ($e) => ['changes.'.$e['field'] => $e['message']])->all())
                ->with('error', 'Some answers are not valid for this form.');
        }

        return back()->with('success', 'Answers updated.');
    }

    public function destroy(Request $request, DataCollectionSubmission $submission): RedirectResponse
    {
        $this->authorize('delete', $submission);

        $reason = $request->validate(['reason' => 'required|string|max:1000'])['reason'];
        $this->review->delete($submission, $request->user(), $reason);

        return back()->with('success', 'Submission deleted. It can be restored from the deleted filter.');
    }

    public function restore(Request $request, int $submission): RedirectResponse
    {
        $submission = DataCollectionSubmission::onlyTrashed()->findOrFail($submission);
        $this->authorize('restore', $submission);

        $this->review->restore($submission, $request->user());

        return back()->with('success', 'Submission restored.');
    }

    // Photos are served only through here, after the same access check.
    public function media(Request $request, DataCollectionSubmissionMedia $media)
    {
        $submission = DataCollectionSubmission::withTrashed()->findOrFail($media->submission_id);
        $this->authorize('view', $submission);

        $hidden = FormFieldCatalog::for($submission->form)->hiddenFor(FormAccess::canSeePii($request->user()));
        abort_if(in_array($media->field_name, $hidden, true), 403);

        return Storage::disk($media->disk)->response($media->path, $media->original_name, [
            'Content-Type'           => $media->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
        ]);
    }

    // Top-level answer fields of the submission's version that can be edited.
    private function editableFields(array $schema, array $hidden): array
    {
        $out = [];
        foreach (SchemaWalker::fields($schema) as $field) {
            if ($field['repeat'] !== null || in_array($field['path'], $hidden, true)
                || in_array($field['type'], ['calculate', 'photo', 'geopoint'], true)) {
                continue;
            }
            $out[] = [
                'name'    => $field['name'],
                'label'   => $field['label'],
                'type'    => $field['type'],
                'choices' => in_array($field['type'], ['select_one', 'select_multiple'], true)
                    ? collect(SchemaWalker::choiceLabels($schema, $field['node']))->map(fn ($label, $name) => ['name' => (string) $name, 'label' => $label])->values()
                    : [],
            ];
        }

        return $out;
    }
}
