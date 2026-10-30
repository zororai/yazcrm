<?php

namespace App\Support\DataCollection;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormAssignment;
use App\Models\User;

// Who may do what with a form. The data-collection roles map onto the CRM's
// own role system:
//   Admin         - CRM role admin/director: everything.
//   Form Designer - nav permission data_collection_design: every permission
//                   on every form, plus upload/deploy/assign.
//   Analyst, Enumerator, Viewer - per-form assignments (to a user or a CRM
//                   role) carrying collect|view|edit|export|map.
// Seeing PII is separate: admins, or nav permission data_collection_pii.
class FormAccess
{
    // Per User object, so it lives no longer than the request/job that
    // loaded the user; cleared whenever an assignment changes.
    private static ?\WeakMap $cache = null;

    public static function isAdmin(?User $user): bool
    {
        return $user && in_array($user->role, ['admin', 'director'], true);
    }

    public static function isDesigner(?User $user): bool
    {
        return self::isAdmin($user) || self::hasNav($user, 'data_collection_design');
    }

    public static function canSeePii(?User $user): bool
    {
        return self::isAdmin($user) || self::hasNav($user, 'data_collection_pii');
    }

    // The user's permissions on one form.
    public static function permissions(?User $user, DataCollectionForm|int $form): array
    {
        if (! $user) {
            return [];
        }
        if (self::isDesigner($user)) {
            return DataCollectionFormAssignment::PERMISSIONS;
        }

        $formId = $form instanceof DataCollectionForm ? $form->id : $form;

        return self::byForm($user)[$formId] ?? [];
    }

    public static function can(?User $user, DataCollectionForm|int $form, string $permission): bool
    {
        return in_array($permission, self::permissions($user, $form), true);
    }

    // Ids of forms the user holds $permission on; null means "all forms".
    public static function formIds(?User $user, string $permission): ?array
    {
        if (self::isDesigner($user)) {
            return null;
        }
        if (! $user) {
            return [];
        }

        return array_keys(array_filter(self::byForm($user), fn ($perms) => in_array($permission, $perms, true)));
    }

    // Any per-form permission at all (for showing the menu).
    public static function hasAnyForm(?User $user): bool
    {
        return self::isDesigner($user) || ($user && self::byForm($user) !== []);
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    // form_id => merged permissions from the user's own and role assignments.
    private static function byForm(User $user): array
    {
        self::$cache ??= new \WeakMap();
        $cached = self::$cache[$user] ?? null;
        if ($cached !== null && $cached['role'] === $user->role) {
            return $cached['forms'];
        }

        $rows = DataCollectionFormAssignment::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('crm_role', $user->role))
            ->get(['form_id', 'permissions']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->form_id] = array_values(array_unique(array_merge($out[$row->form_id] ?? [], $row->permissions ?? [])));
        }

        self::$cache[$user] = ['role' => $user->role, 'forms' => $out];

        return $out;
    }

    private static function hasNav(?User $user, string $key): bool
    {
        return $user && in_array($key, $user->nav_permissions ?? [], true);
    }
}
