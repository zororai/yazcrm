<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolSubject;
use App\Models\SchoolTeachingAssignment;
use App\Support\SchoolAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// School admin only: classes (with class teacher), subjects, and which
// teacher takes which subject in which class.
class SchoolSetupController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(SchoolAccess::isAdmin($request->user()), 403);
    }

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('School/Setup', [
            'classes'     => SchoolClass::with('classTeacher:id,name')->withCount('students')->orderByDesc('academic_year')->orderBy('name')->get(),
            'subjects'    => SchoolSubject::orderBy('name')->get(),
            'assignments' => SchoolTeachingAssignment::with(['schoolClass:id,name', 'subject:id,name', 'teacher:id,name'])->get(),
            'teachers'    => SchoolAccess::teachers(),
            'currentYear' => SchoolAccess::currentYear(),
        ]);
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        SchoolClass::create($this->validateClass($request));

        return back()->with('success', 'Course created.');
    }

    public function updateClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $schoolClass->update($this->validateClass($request));

        return back()->with('success', 'Course saved.');
    }

    public function destroyClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $schoolClass->delete();

        return back()->with('success', 'Course deleted.');
    }

    private function validateClass(Request $request): array
    {
        return $request->validate([
            'name'             => 'required|string|max:255',
            'grade_level'      => 'nullable|string|max:50',
            'academic_year'    => 'required|integer|min:2000|max:2100',
            'class_teacher_id' => 'nullable|exists:users,id',
            'is_active'        => 'sometimes|boolean',
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        SchoolSubject::create($this->validateSubject($request));

        return back()->with('success', 'Module created.');
    }

    public function updateSubject(Request $request, SchoolSubject $subject): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $subject->update($this->validateSubject($request));

        return back()->with('success', 'Module saved.');
    }

    public function destroySubject(Request $request, SchoolSubject $subject): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $subject->delete();

        return back()->with('success', 'Module deleted.');
    }

    private function validateSubject(Request $request): array
    {
        return $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);
    }

    // One teacher per subject per class — assigning again replaces them.
    public function storeAssignment(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'school_class_id'   => 'required|exists:school_classes,id',
            'school_subject_id' => 'required|exists:school_subjects,id',
            'teacher_id'        => 'required|exists:users,id',
        ]);

        SchoolTeachingAssignment::updateOrCreate(
            ['school_class_id' => $data['school_class_id'], 'school_subject_id' => $data['school_subject_id']],
            ['teacher_id' => $data['teacher_id']],
        );

        return back()->with('success', 'Facilitator assigned.');
    }

    public function destroyAssignment(Request $request, SchoolTeachingAssignment $assignment): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $assignment->delete();

        return back()->with('success', 'Assignment removed.');
    }
}
