<?php

namespace App\Support\Leave;

use App\Models\Leave\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

// Who may do what with leave. The app shows everyone the same screens; this
// is the only place that decides.
//  • admin / director, or the leave_admin nav permission: everything
//  • leave_approve nav permission: decide requests from their direct reports
//    (users whose supervisor_id is them) and see the team calendar
//  • anyone else who can log in: their own requests and balances
// Nobody decides or corrects their own request, admins included.
class LeaveAccess
{
    public static function isAdmin(?User $user): bool
    {
        return $user && (in_array($user->role, ['admin', 'director'], true) || self::hasNav($user, 'leave_admin'));
    }

    public static function isApprover(?User $user): bool
    {
        return $user && self::hasNav($user, 'leave_approve');
    }

    public static function manages(?User $user, LeaveRequest $request): bool
    {
        $requester = $request->relationLoaded('user') ? $request->user : $request->user()->first();

        return $user && $requester && (int) $requester->supervisor_id === (int) $user->id;
    }

    public static function canView(?User $user, LeaveRequest $request): bool
    {
        return $user && ((int) $request->user_id === (int) $user->id
            || self::isAdmin($user)
            || (self::isApprover($user) && self::manages($user, $request)));
    }

    public static function canDecide(?User $user, LeaveRequest $request): bool
    {
        return $user && (int) $request->user_id !== (int) $user->id
            && (self::isAdmin($user) || (self::isApprover($user) && self::manages($user, $request)));
    }

    // Requests this user may decide: all of them for admins, direct reports'
    // for approvers, none for anyone else. Never their own.
    public static function decidable(User $user): Builder
    {
        $query = LeaveRequest::query()->where('user_id', '!=', $user->id);

        if (self::isAdmin($user)) {
            return $query;
        }

        if (self::isApprover($user)) {
            return $query->whereHas('user', fn ($q) => $q->where('supervisor_id', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }

    // Requests that show on this user's team calendar: everyone's for admins,
    // their own and their direct reports' for approvers.
    public static function calendarScope(User $user): Builder
    {
        $query = LeaveRequest::query();

        if (self::isAdmin($user)) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('user_id', $user->id)
            ->orWhereHas('user', fn ($u) => $u->where('supervisor_id', $user->id)));
    }

    private static function hasNav(User $user, string $key): bool
    {
        return in_array($key, $user->nav_permissions ?? [], true);
    }
}
