<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionFormAssignment;
use App\Models\DataCollectionSubmission;
use App\Models\User;
use App\Services\DataCollection\DataCollectionSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DataCollectionSubmissionController extends Controller
{
    public function __construct(private readonly DataCollectionSubmissionService $service)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    private function canAccess(User $user, DataCollectionSubmission $submission): bool
    {
        return $this->isManager($user) || $submission->submitted_by === $user->id;
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $assignments = DataCollectionFormAssignment::with(['form:id,name,project_id', 'form.project:id,name'])
            ->where('assigned_to', $user->id)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->latest()
            ->get();

        $submissions = DataCollectionSubmission::with(['form:id,name'])
            ->where('submitted_by', $user->id)
            ->latest()
            ->get();

        return Inertia::render('DataCollection/MyCollection', [
            'assignments' => $assignments,
            'submissions' => $submissions,
        ]);
    }

    public function start(Request $request, DataCollectionFormAssignment $assignment): RedirectResponse
    {
        try {
            $submission = $this->service->startSubmission($assignment, $request->user());
        } catch (RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->route('data-collection.submissions.show', $submission);
    }

    public function show(Request $request, DataCollectionSubmission $submission): Response
    {
        if (! $this->canAccess($request->user(), $submission)) {
            abort(403);
        }

        return Inertia::render('DataCollection/Submissions/Show', [
            'submission' => $submission->load(['form:id,name', 'formVersion', 'submittedBy:id,name']),
            'canEdit'    => $submission->status === 'draft' && $submission->submitted_by === $request->user()->id,
        ]);
    }

    public function update(Request $request, DataCollectionSubmission $submission): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $submission)) {
            abort(403);
        }

        $data = $request->validate(['answers' => 'required|array']);

        try {
            $this->service->saveDraft($submission, $request->user(), $data['answers']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Draft saved.');
    }

    public function submit(Request $request, DataCollectionSubmission $submission): RedirectResponse
    {
        if (! $this->canAccess($request->user(), $submission)) {
            abort(403);
        }

        try {
            $this->service->submit($submission, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Submission completed.');
    }
}
