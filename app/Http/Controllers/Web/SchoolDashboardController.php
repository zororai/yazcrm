<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolSchemeTopic;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentResult;
use App\Models\SchoolTeacherAttendance;
use App\Support\SchoolAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

// Termly overview: today's registers, teacher attendance and syllabus
// coverage, class averages, and students whose attendance is slipping.
// Teachers see the same view limited to their own classes and themselves.
class SchoolDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user     = $request->user();
        $isAdmin  = SchoolAccess::isAdmin($user);
        $classIds = SchoolAccess::classIds($user);
        $year     = $request->integer('year') ?: SchoolAccess::currentYear();
        $term     = $request->integer('term') ?: SchoolAccess::currentTerm();
        $today    = now()->toDateString();

        // Term = four calendar months (see SchoolAccess::currentTerm).
        $termStart = Carbon::create($year, $term * 4 - 3, 1)->startOfDay();
        $termEnd   = $termStart->copy()->addMonths(3)->endOfMonth();

        $classes = SchoolClass::whereIn('id', $classIds)->where('is_active', true)
            ->with('classTeacher:id,name')
            ->withCount(['students' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        // ── Today's registers ──
        $todayCounts = SchoolStudentAttendance::whereIn('school_class_id', $classIds)
            ->whereDate('date', $today)
            ->selectRaw('school_class_id, status, COUNT(*) as total')
            ->groupBy('school_class_id', 'status')
            ->get()
            ->groupBy('school_class_id');

        $registers = $classes->map(function (SchoolClass $c) use ($todayCounts) {
            $counts = ($todayCounts[$c->id] ?? collect())->pluck('total', 'status');

            return [
                'id'            => $c->id,
                'name'          => $c->name,
                'class_teacher' => $c->classTeacher?->name,
                'students'      => $c->students_count,
                'marked'        => (int) $counts->sum(),
                'present'       => (int) (($counts['present'] ?? 0) + ($counts['late'] ?? 0)),
                'absent'        => (int) ($counts['absent'] ?? 0),
            ];
        });

        // ── Student attendance this term, lowest first ──
        $atRisk = SchoolStudentAttendance::whereIn('school_class_id', $classIds)
            ->whereBetween('date', [$termStart, $termEnd])
            ->selectRaw("school_student_id, COUNT(*) as days, SUM(status IN ('present','late')) as attended")
            ->groupBy('school_student_id')
            ->havingRaw('days >= 5 AND attended / days < 0.8')
            ->orderByRaw('attended / days')
            ->limit(15)
            ->get();
        $atRiskStudents = SchoolStudent::with('schoolClass:id,name')->whereIn('id', $atRisk->pluck('school_student_id'))->get()->keyBy('id');
        $atRisk = $atRisk->map(fn ($r) => [
            'id'    => $r->school_student_id,
            'name'  => $atRiskStudents[$r->school_student_id]?->full_name,
            'class' => $atRiskStudents[$r->school_student_id]?->schoolClass?->name,
            'rate'  => round($r->attended / $r->days * 100),
            'days'  => (int) $r->days,
        ])->values();

        // ── Teacher progress: scheme-of-work coverage + attendance this term ──
        $topicRows = SchoolSchemeTopic::with(['teacher:id,name', 'schoolClass:id,name', 'subject:id,name'])
            ->whereIn('school_class_id', $classIds)
            ->where('academic_year', $year)
            ->where('term', $term)
            ->when(! $isAdmin, fn ($q) => $q->where('teacher_id', $user->id))
            ->get();

        $coverage = $topicRows
            ->groupBy(fn ($t) => "{$t->teacher_id}|{$t->school_class_id}|{$t->school_subject_id}")
            ->map(function ($topics) use ($today) {
                $first     = $topics->first();
                $planned   = $topics->where('status', '!=', 'skipped')->count();
                $completed = $topics->where('status', 'completed')->count();

                return [
                    'teacher'   => $first->teacher?->name ?? '—',
                    'class'     => $first->schoolClass?->name,
                    'subject'   => $first->subject?->name,
                    'class_id'  => $first->school_class_id,
                    'subject_id'=> $first->school_subject_id,
                    'planned'   => $planned,
                    'completed' => $completed,
                    'overdue'   => $topics->filter(fn ($t) => $t->planned_date
                        && $t->planned_date->toDateString() < $today
                        && ! in_array($t->status, ['completed', 'skipped'], true))->count(),
                    'percent'   => $planned ? round($completed / $planned * 100) : 0,
                ];
            })
            ->sortBy('percent')
            ->values();

        $teacherAttendance = SchoolTeacherAttendance::with('teacher:id,name')
            ->whereBetween('date', [$termStart, $termEnd])
            ->when(! $isAdmin, fn ($q) => $q->where('teacher_id', $user->id))
            ->selectRaw("teacher_id, COUNT(*) as days, SUM(status = 'present') as present, SUM(status = 'late') as late, SUM(status = 'absent') as absent, SUM(status = 'leave') as on_leave")
            ->groupBy('teacher_id')
            ->get()
            ->map(fn ($r) => [
                'teacher' => $r->teacher?->name,
                'days'    => (int) $r->days,
                'present' => (int) $r->present,
                'late'    => (int) $r->late,
                'absent'  => (int) $r->absent,
                'leave'   => (int) $r->on_leave,
            ]);

        $teachersToday = $isAdmin
            ? SchoolTeacherAttendance::whereDate('date', $today)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            : null;

        // ── Class averages this term ──
        $averages = SchoolStudentResult::with(['subject:id,name'])
            ->whereIn('school_class_id', $classIds)
            ->where('academic_year', $year)
            ->where('term', $term)
            ->selectRaw('school_class_id, school_subject_id, AVG(mark) as average, COUNT(DISTINCT school_student_id) as students')
            ->groupBy('school_class_id', 'school_subject_id')
            ->get()
            ->map(fn ($r) => [
                'class'    => $classes->firstWhere('id', $r->school_class_id)?->name,
                'subject'  => $r->subject?->name,
                'average'  => round((float) $r->average, 1),
                'students' => (int) $r->students,
            ])
            ->sortBy(['class', 'subject'])
            ->values();

        return Inertia::render('School/Dashboard', [
            'isAdmin'           => $isAdmin,
            'year'              => $year,
            'term'              => $term,
            'registers'         => $registers,
            'atRisk'            => $atRisk,
            'coverage'          => $coverage,
            'teacherAttendance' => $teacherAttendance,
            'teachersToday'     => $teachersToday,
            'teacherCount'      => $isAdmin ? SchoolAccess::teachers()->count() : null,
            'averages'          => $averages,
        ]);
    }
}
