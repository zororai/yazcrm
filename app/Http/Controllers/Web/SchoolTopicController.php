<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SchoolSchemeTopic;
use App\Models\SchoolTeachingAssignment;
use App\Support\SchoolAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// Scheme of work: the topics a teacher plans to teach in a class/subject
// for a term, ticked off as they are taught. Coverage (completed ÷ planned)
// is how teacher progress is measured.
class SchoolTopicController extends Controller
{
    public function index(Request $request): Response
    {
        $user      = $request->user();
        $options   = SchoolAccess::classSubjectOptions($user);
        $classId   = $request->integer('class_id') ?: ($options[0]['id'] ?? null);
        $subjectId = $request->integer('subject_id') ?: null;
        $year      = $request->integer('year') ?: SchoolAccess::currentYear();
        $term      = $request->integer('term') ?: SchoolAccess::currentTerm();

        if ($classId && ! $subjectId) {
            $subjectId = collect($options)->firstWhere('id', $classId)['subjects'][0]['id'] ?? null;
        }

        $topics = collect();
        if ($classId && $subjectId) {
            abort_unless(SchoolAccess::canTeach($user, $classId, $subjectId), 403);

            $topics = SchoolSchemeTopic::with('teacher:id,name')
                ->where([
                    'school_class_id'   => $classId,
                    'school_subject_id' => $subjectId,
                    'academic_year'     => $year,
                    'term'              => $term,
                ])
                ->orderByRaw('week IS NULL, week')
                ->orderBy('planned_date')
                ->orderBy('id')
                ->get();
        }

        return Inertia::render('School/Topics', [
            'options' => $options,
            'filters' => compact('classId', 'subjectId', 'year', 'term'),
            'topics'  => $topics,
            'today'   => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        abort_unless(SchoolAccess::canTeach($request->user(), $data['school_class_id'], $data['school_subject_id']), 403);

        // Credit the subject teacher if one is assigned, else whoever planned it.
        $data['teacher_id'] = SchoolTeachingAssignment::where([
            'school_class_id'   => $data['school_class_id'],
            'school_subject_id' => $data['school_subject_id'],
        ])->value('teacher_id') ?? $request->user()->id;

        SchoolSchemeTopic::create($data);

        return back()->with('success', 'Topic added.');
    }

    public function update(Request $request, SchoolSchemeTopic $topic): RedirectResponse
    {
        abort_unless(SchoolAccess::canTeach($request->user(), $topic->school_class_id, $topic->school_subject_id), 403);

        $data = $request->validate([
            'week'           => 'nullable|integer|min:1|max:20',
            'topic'          => 'sometimes|required|string|max:255',
            'objectives'     => 'nullable|string',
            'planned_date'   => 'nullable|date',
            'status'         => ['sometimes', Rule::in(['planned', 'in_progress', 'completed', 'skipped'])],
            'completed_date' => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        if (($data['status'] ?? null) === 'completed' && empty($data['completed_date']) && ! $topic->completed_date) {
            $data['completed_date'] = now()->toDateString();
        }
        if (isset($data['status']) && $data['status'] !== 'completed') {
            $data['completed_date'] = null;
        }

        $topic->update($data);

        return back()->with('success', 'Topic updated.');
    }

    public function destroy(Request $request, SchoolSchemeTopic $topic): RedirectResponse
    {
        abort_unless(SchoolAccess::canTeach($request->user(), $topic->school_class_id, $topic->school_subject_id), 403);
        $topic->delete();

        return back()->with('success', 'Topic removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'school_class_id'   => 'required|exists:school_classes,id',
            'school_subject_id' => 'required|exists:school_subjects,id',
            'academic_year'     => 'required|integer|min:2000|max:2100',
            'term'              => 'required|integer|between:1,3',
            'week'              => 'nullable|integer|min:1|max:20',
            'topic'             => 'required|string|max:255',
            'objectives'        => 'nullable|string',
            'planned_date'      => 'nullable|date',
        ]);

        $data['school_class_id']   = (int) $data['school_class_id'];
        $data['school_subject_id'] = (int) $data['school_subject_id'];

        return $data;
    }
}
