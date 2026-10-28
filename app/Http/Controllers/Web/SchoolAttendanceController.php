<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolTeacherAttendance;
use App\Support\SchoolAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// Daily class register (students) and staff sign-in (teachers).
class SchoolAttendanceController extends Controller
{
    // ── Class register ──

    public function register(Request $request): Response
    {
        $user     = $request->user();
        $classes  = SchoolClass::whereIn('id', SchoolAccess::classIds($user))->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $classId  = $request->integer('class_id') ?: $classes->first()?->id;
        $date     = $request->query('date', now()->toDateString());

        $students = collect();
        if ($classId) {
            abort_unless(SchoolAccess::canAccessClass($user, $classId), 403);

            $marked = SchoolStudentAttendance::where('school_class_id', $classId)
                ->whereDate('date', $date)
                ->get()
                ->keyBy('school_student_id');

            $students = SchoolStudent::where('school_class_id', $classId)
                ->where('is_active', true)
                ->orderBy('surname')
                ->orderBy('first_name')
                ->get(['id', 'admission_number', 'first_name', 'surname', 'gender'])
                ->map(fn (SchoolStudent $s) => [
                    'id'               => $s->id,
                    'admission_number' => $s->admission_number,
                    'full_name'        => $s->full_name,
                    'status'           => $marked[$s->id]->status ?? null,
                    'note'             => $marked[$s->id]->note ?? null,
                ]);
        }

        return Inertia::render('School/Attendance', [
            'classes'  => $classes,
            'classId'  => $classId,
            'date'     => $date,
            'students' => $students,
        ]);
    }

    public function saveRegister(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id'          => 'required|exists:school_classes,id',
            'date'              => 'required|date|before_or_equal:today',
            'entries'           => 'required|array',
            'entries.*.id'      => 'required|exists:school_students,id',
            'entries.*.status'  => ['nullable', Rule::in(['present', 'absent', 'late', 'excused'])],
            'entries.*.note'    => 'nullable|string|max:255',
        ]);

        abort_unless(SchoolAccess::canAccessClass($request->user(), (int) $data['class_id']), 403);

        $studentIds = SchoolStudent::where('school_class_id', $data['class_id'])->pluck('id');

        DB::transaction(function () use ($data, $request, $studentIds) {
            foreach ($data['entries'] as $entry) {
                if (! $studentIds->contains($entry['id'])) {
                    continue; // not in this class — ignore rather than let a register write elsewhere
                }

                $key = ['school_student_id' => $entry['id'], 'date' => $data['date']];

                if (empty($entry['status'])) {
                    SchoolStudentAttendance::where($key)->delete();
                    continue;
                }

                SchoolStudentAttendance::updateOrCreate($key, [
                    'school_class_id' => $data['class_id'],
                    'status'          => $entry['status'],
                    'note'            => $entry['note'] ?? null,
                    'recorded_by'     => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', 'Register saved.');
    }

    // ── Teacher attendance ──

    public function teachers(Request $request): Response
    {
        $user    = $request->user();
        $isAdmin = SchoolAccess::isAdmin($user);
        $date    = $request->query('date', now()->toDateString());

        $records = SchoolTeacherAttendance::whereDate('date', $date)->get()->keyBy('teacher_id');

        $teachers = ($isAdmin ? SchoolAccess::teachers() : collect([$user->only('id', 'name', 'email')]))
            ->map(fn ($t) => [
                'id'       => $t['id'],
                'name'     => $t['name'],
                'status'   => $records[$t['id']]->status ?? null,
                'time_in'  => $this->hm($records[$t['id']]->time_in ?? null),
                'time_out' => $this->hm($records[$t['id']]->time_out ?? null),
                'note'     => $records[$t['id']]->note ?? null,
            ])
            ->values();

        $today = SchoolTeacherAttendance::where('teacher_id', $user->id)->whereDate('date', now()->toDateString())->first();

        return Inertia::render('School/TeacherAttendance', [
            'date'     => $date,
            'teachers' => $teachers,
            'isAdmin'  => $isAdmin,
            'myToday'  => $today ? [
                'time_in'  => $this->hm($today->time_in),
                'time_out' => $this->hm($today->time_out),
                'status'   => $today->status,
            ] : null,
        ]);
    }

    public function saveTeachers(Request $request): RedirectResponse
    {
        abort_unless(SchoolAccess::isAdmin($request->user()), 403);

        $data = $request->validate([
            'date'               => 'required|date',
            'entries'            => 'required|array',
            'entries.*.id'       => 'required|exists:users,id',
            'entries.*.status'   => ['nullable', Rule::in(['present', 'absent', 'late', 'leave'])],
            'entries.*.time_in'  => 'nullable|date_format:H:i',
            'entries.*.time_out' => 'nullable|date_format:H:i',
            'entries.*.note'     => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['entries'] as $entry) {
                $key = ['teacher_id' => $entry['id'], 'date' => $data['date']];

                if (empty($entry['status'])) {
                    SchoolTeacherAttendance::where($key)->delete();
                    continue;
                }

                SchoolTeacherAttendance::updateOrCreate($key, [
                    'status'      => $entry['status'],
                    'time_in'     => $entry['time_in'] ?? null,
                    'time_out'    => $entry['time_out'] ?? null,
                    'note'        => $entry['note'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', 'Teacher attendance saved.');
    }

    // A teacher's own sign-in/sign-out for today. Signing in after the
    // school's start time records them as late.
    public function signIn(Request $request): RedirectResponse
    {
        $now    = now();
        $record = SchoolTeacherAttendance::firstOrNew(['teacher_id' => $request->user()->id, 'date' => $now->toDateString()]);

        if (! $record->time_in) {
            $record->fill([
                'time_in'     => $now->format('H:i'),
                'status'      => $now->format('H:i') > config('school.start_time') ? 'late' : 'present',
                'recorded_by' => $request->user()->id,
            ])->save();
        }

        return back()->with('success', 'Signed in at ' . $this->hm($record->time_in) . '.');
    }

    public function signOut(Request $request): RedirectResponse
    {
        $record = SchoolTeacherAttendance::where('teacher_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $record) {
            return back()->with('error', 'Sign in first.');
        }

        $record->update(['time_out' => now()->format('H:i')]);

        return back()->with('success', 'Signed out.');
    }

    // TIME columns come back as "07:30:00"; the UI works in HH:MM.
    private function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
