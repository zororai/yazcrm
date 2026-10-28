<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentResult;
use App\Support\SchoolAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// Student register. Teachers see (and may add/edit) students in their own
// classes; school admins see everyone.
class SchoolStudentController extends Controller
{
    public function index(Request $request): Response
    {
        $user     = $request->user();
        $classIds = SchoolAccess::classIds($user);
        $classId  = $request->integer('class_id') ?: null;
        $search   = trim((string) $request->query('search', ''));

        $students = SchoolStudent::with('schoolClass:id,name')
            ->when(! SchoolAccess::isAdmin($user), fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('surname', 'like', "%{$search}%")
                ->orWhere('admission_number', 'like', "%{$search}%")))
            ->orderBy('surname')
            ->orderBy('first_name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('School/Students', [
            'students' => $students,
            'classes'  => SchoolClass::whereIn('id', $classIds)->orderBy('name')->get(['id', 'name']),
            'filters'  => ['class_id' => $classId, 'search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SchoolStudent::create($this->validated($request));

        return back()->with('success', 'Participant added.');
    }

    public function update(Request $request, SchoolStudent $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        $student->update($this->validated($request, $student));

        return back()->with('success', 'Participant saved.');
    }

    public function destroy(Request $request, SchoolStudent $student): RedirectResponse
    {
        abort_unless(SchoolAccess::isAdmin($request->user()), 403);
        $student->delete();

        return back()->with('success', 'Participant deleted.');
    }

    // Per-student report: attendance summary for the year and marks per
    // subject per term.
    public function show(Request $request, SchoolStudent $student): Response
    {
        $this->authorizeStudent($request, $student);
        $year = $request->integer('year') ?: SchoolAccess::currentYear();

        $attendance = SchoolStudentAttendance::where('school_student_id', $student->id)
            ->whereYear('date', $year)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $results = SchoolStudentResult::with('subject:id,name')
            ->where('school_student_id', $student->id)
            ->where('academic_year', $year)
            ->orderBy('term')
            ->get(['id', 'school_subject_id', 'term', 'assessment', 'mark', 'comment']);

        $absences = SchoolStudentAttendance::where('school_student_id', $student->id)
            ->whereYear('date', $year)
            ->whereIn('status', ['absent', 'late'])
            ->orderByDesc('date')
            ->limit(30)
            ->get(['date', 'status', 'note']);

        return Inertia::render('School/StudentReport', [
            'student'    => $student->load('schoolClass:id,name'),
            'year'       => $year,
            'attendance' => $attendance,
            'results'    => $results,
            'absences'   => $absences,
        ]);
    }

    private function authorizeStudent(Request $request, SchoolStudent $student): void
    {
        $user = $request->user();
        abort_unless(
            SchoolAccess::isAdmin($user)
                || ($student->school_class_id && SchoolAccess::canAccessClass($user, $student->school_class_id)),
            403
        );
    }

    private function validated(Request $request, ?SchoolStudent $student = null): array
    {
        $data = $request->validate([
            'admission_number' => ['required', 'string', 'max:50', Rule::unique('school_students')->ignore($student)],
            'first_name'       => 'required|string|max:255',
            'surname'          => 'required|string|max:255',
            'gender'           => ['nullable', Rule::in(['M', 'F'])],
            'date_of_birth'    => 'nullable|date',
            'school_class_id'  => 'nullable|exists:school_classes,id',
            'guardian_name'    => 'nullable|string|max:255',
            'guardian_phone'   => 'nullable|string|max:50',
            'is_active'        => 'sometimes|boolean',
        ], [], [
            // Shown in validation messages (the module is presented as Courses).
            'admission_number' => 'participant number',
            'school_class_id'  => 'course',
            'guardian_name'    => 'next of kin name',
            'guardian_phone'   => 'next of kin phone',
        ]);

        // Teachers may only place students in classes they have access to.
        $user = $request->user();
        if (! SchoolAccess::isAdmin($user)) {
            abort_unless(
                $data['school_class_id'] && SchoolAccess::canAccessClass($user, (int) $data['school_class_id']),
                403
            );
        }

        return $data;
    }
}
