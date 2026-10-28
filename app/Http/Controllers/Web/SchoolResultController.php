<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentResult;
use App\Support\SchoolAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Marks entry: pick class, subject, term and assessment, then type a mark
// (percentage) per student. Class average shown alongside.
class SchoolResultController extends Controller
{
    public function index(Request $request): Response
    {
        $user       = $request->user();
        $options    = SchoolAccess::classSubjectOptions($user);
        $classId    = $request->integer('class_id') ?: ($options[0]['id'] ?? null);
        $subjectId  = $request->integer('subject_id') ?: null;
        $year       = $request->integer('year') ?: SchoolAccess::currentYear();
        $term       = $request->integer('term') ?: SchoolAccess::currentTerm();
        $assessment = $request->query('assessment', 'End of term');

        if ($classId && ! $subjectId) {
            $subjectId = collect($options)->firstWhere('id', $classId)['subjects'][0]['id'] ?? null;
        }

        $students = collect();
        if ($classId && $subjectId) {
            abort_unless(SchoolAccess::canTeach($user, $classId, $subjectId), 403);

            $marks = SchoolStudentResult::where([
                'school_class_id'   => $classId,
                'school_subject_id' => $subjectId,
                'academic_year'     => $year,
                'term'              => $term,
                'assessment'        => $assessment,
            ])->get()->keyBy('school_student_id');

            $students = SchoolStudent::where('school_class_id', $classId)
                ->where('is_active', true)
                ->orderBy('surname')
                ->orderBy('first_name')
                ->get()
                ->map(fn (SchoolStudent $s) => [
                    'id'               => $s->id,
                    'admission_number' => $s->admission_number,
                    'full_name'        => $s->full_name,
                    'mark'             => $marks[$s->id]->mark ?? null,
                    'comment'          => $marks[$s->id]->comment ?? null,
                ]);
        }

        $assessments = SchoolStudentResult::distinct()->orderBy('assessment')->pluck('assessment')
            ->merge(['Mid-term', 'End of term'])->unique()->values();

        return Inertia::render('School/Results', [
            'options'     => $options,
            'filters'     => compact('classId', 'subjectId', 'year', 'term', 'assessment'),
            'students'    => $students,
            'assessments' => $assessments,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id'          => 'required|exists:school_classes,id',
            'subject_id'        => 'required|exists:school_subjects,id',
            'year'              => 'required|integer|min:2000|max:2100',
            'term'              => 'required|integer|between:1,3',
            'assessment'        => 'required|string|max:50',
            'entries'           => 'required|array',
            'entries.*.id'      => 'required|exists:school_students,id',
            'entries.*.mark'    => 'nullable|numeric|between:0,100',
            'entries.*.comment' => 'nullable|string|max:255',
        ]);

        abort_unless(SchoolAccess::canTeach($request->user(), (int) $data['class_id'], (int) $data['subject_id']), 403);

        $studentIds = SchoolStudent::where('school_class_id', $data['class_id'])->pluck('id');

        DB::transaction(function () use ($data, $request, $studentIds) {
            foreach ($data['entries'] as $entry) {
                if (! $studentIds->contains($entry['id'])) {
                    continue;
                }

                $key = [
                    'school_student_id' => $entry['id'],
                    'school_subject_id' => $data['subject_id'],
                    'academic_year'     => $data['year'],
                    'term'              => $data['term'],
                    'assessment'        => $data['assessment'],
                ];

                if ($entry['mark'] === null || $entry['mark'] === '') {
                    SchoolStudentResult::where($key)->delete();
                    continue;
                }

                SchoolStudentResult::updateOrCreate($key, [
                    'school_class_id' => $data['class_id'],
                    'mark'            => $entry['mark'],
                    'comment'         => $entry['comment'] ?? null,
                    'recorded_by'     => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', 'Marks saved.');
    }
}
