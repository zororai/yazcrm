<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionFormAssignment;
use App\Models\DataCollectionSubmission;
use App\Services\DataCollection\DataCollectionSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use RuntimeException;

// Kobo-style field collection from the beneficiary app: download the forms
// assigned to the logged-in user, fill them offline, bulk-sync the answers.
// Goes through DataCollectionSubmissionService so app submissions follow the
// same rules (ownership, schema validation, activity log) as the web flow.
#[OA\Tag(name: 'Data Collection (App)', description: 'Offline form filling in the beneficiary app')]
class DataCollectionController extends Controller
{
    public function __construct(private readonly DataCollectionSubmissionService $service)
    {
    }

    // 'completed' stays in the list: an officer collects the same form from
    // many respondents, so one submission doesn't close it on the device.
    #[OA\Get(
        path: '/api/data-collection/my-forms',
        tags: ['Data Collection (App)'],
        summary: "The logged-in user's form assignments, each with its form version schema",
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of assignments'),
        ],
    )]
    public function myForms(Request $request): JsonResponse
    {
        $assignments = DataCollectionFormAssignment::with([
            'form:id,name,description,project_id',
            'form.project:id,name',
            'formVersion:id,version_number,version_label,schema',
        ])
            ->where('assigned_to', $request->user()->id)
            ->whereIn('status', ['assigned', 'in_progress', 'completed'])
            ->latest()
            ->get();

        return response()->json([
            'assignments' => $assignments->map(fn (DataCollectionFormAssignment $a) => [
                'assignment_id'   => $a->id,
                'form_id'         => $a->form_id,
                'form_name'       => $a->form?->name,
                'form_description' => $a->form?->description,
                'project_name'    => $a->form?->project?->name,
                'form_version_id' => $a->form_version_id,
                'version_label'   => $a->formVersion?->version_label ?? 'v'.$a->formVersion?->version_number,
                'schema'          => $a->formVersion?->schema,
                'due_date'        => $a->due_date?->toDateString(),
                'status'          => $a->status,
            ])->values(),
        ]);
    }

    // Idempotent via submission_uid (generated on the device). Each record is
    // its own transaction, so one invalid submission doesn't block the rest.
    #[OA\Post(
        path: '/api/data-collection/submissions/sync',
        tags: ['Data Collection (App)'],
        summary: 'Bulk-upload completed submissions captured offline',
        description: 'Idempotent via submission_uid — safe to retry the same batch.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Per-submission result: created, skipped or failed'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'submissions'                  => 'required|array|min:1',
            'submissions.*.submission_uid' => 'required|uuid',
            'submissions.*.assignment_id'  => 'required|integer',
            'submissions.*.answers'        => 'present|array',
            'submissions.*.started_at'     => 'nullable|date',
        ]);

        $user    = $request->user();
        $results = [];

        foreach ($data['submissions'] as $item) {
            $uid = $item['submission_uid'];

            if (DataCollectionSubmission::where('submission_uid', $uid)->exists()) {
                $results[] = ['submission_uid' => $uid, 'status' => 'skipped'];
                continue;
            }

            $assignment = DataCollectionFormAssignment::find($item['assignment_id']);
            if (! $assignment) {
                $results[] = ['submission_uid' => $uid, 'status' => 'failed', 'message' => 'This form assignment no longer exists.'];
                continue;
            }

            try {
                DB::transaction(function () use ($assignment, $user, $item, $uid) {
                    $submission = $this->service->startSubmission($assignment, $user);
                    $submission->update([
                        'submission_uid' => $uid,
                        'started_at'     => $item['started_at'] ?? $submission->started_at,
                    ]);
                    $this->service->saveDraft($submission, $user, $item['answers']);
                    $this->service->submit($submission->fresh(), $user);
                });
                $results[] = ['submission_uid' => $uid, 'status' => 'created'];
            } catch (RuntimeException $e) {
                $results[] = ['submission_uid' => $uid, 'status' => 'failed', 'message' => $e->getMessage()];
            }
        }

        return response()->json(['message' => 'Sync complete.', 'results' => $results]);
    }
}
