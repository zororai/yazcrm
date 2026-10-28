<?php

namespace App\Support;

use App\Models\SchoolClass;
use App\Models\SchoolSubject;
use App\Models\SchoolTeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

// Who may see/record what in the School module. School admins (CRM admins,
// or anyone with the 'school_admin' nav permission) see everything. A
// teacher sees the classes they are class teacher of or teach a subject in;
// within a class, the class teacher may handle every subject, a subject
// teacher only their own.
class SchoolAccess
{
    public static function isAdmin(User $user): bool
    {
        return $user->role === 'admin' || in_array('school_admin', $user->nav_permissions ?? [], true);
    }

    /** @return Collection<int, int> */
    public static function classIds(User $user): Collection
    {
        if (self::isAdmin($user)) {
            return SchoolClass::pluck('id');
        }

        return SchoolClass::where('class_teacher_id', $user->id)->pluck('id')
            ->merge(SchoolTeachingAssignment::where('teacher_id', $user->id)->pluck('school_class_id'))
            ->unique()
            ->values();
    }

    public static function canAccessClass(User $user, int $classId): bool
    {
        return self::classIds($user)->contains($classId);
    }

    public static function canTeach(User $user, int $classId, int $subjectId): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        return SchoolClass::whereKey($classId)->where('class_teacher_id', $user->id)->exists()
            || SchoolTeachingAssignment::where([
                'school_class_id'   => $classId,
                'school_subject_id' => $subjectId,
                'teacher_id'        => $user->id,
            ])->exists();
    }

    /**
     * Class → subjects the user may handle, for the class/subject pickers.
     *
     * @return array<int, array{id:int, name:string, subjects:array}>
     */
    public static function classSubjectOptions(User $user): array
    {
        $isAdmin  = self::isAdmin($user);
        $subjects = SchoolSubject::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return SchoolClass::whereIn('id', self::classIds($user))
            ->where('is_active', true)
            ->with('assignments')
            ->orderBy('name')
            ->get()
            ->map(function (SchoolClass $class) use ($user, $isAdmin, $subjects) {
                $allowed = $isAdmin || (int) $class->class_teacher_id === $user->id
                    ? $subjects
                    : $subjects->whereIn('id', $class->assignments->where('teacher_id', $user->id)->pluck('school_subject_id'));

                return [
                    'id'       => $class->id,
                    'name'     => $class->name,
                    'subjects' => $allowed->values()->all(),
                ];
            })
            ->all();
    }

    // Teachers = users granted the 'school' permission, plus anyone already
    // set as a class or subject teacher.
    public static function teachers(): Collection
    {
        $assigned = SchoolClass::whereNotNull('class_teacher_id')->pluck('class_teacher_id')
            ->merge(SchoolTeachingAssignment::pluck('teacher_id'));

        return User::where('is_active', true)
            ->where(fn ($q) => $q->whereJsonContains('nav_permissions', 'school')->orWhereIn('id', $assigned))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public static function currentYear(): int
    {
        return (int) now()->year;
    }

    // Zimbabwe school calendar: Jan–Apr term 1, May–Aug term 2, Sep–Dec term 3.
    public static function currentTerm(): int
    {
        return (int) ceil(now()->month / 4);
    }
}
