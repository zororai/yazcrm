<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // These sections used to be unconditionally visible to every authenticated
    // user; they are now permission-gated like the rest of the sidebar so they
    // can be toggled per role. Backfill existing roles/users so nobody's sidebar
    // silently loses items they already had access to.
    private array $coreKeys = [
        'dashboard', 'dialer', 'calls', 'recordings', 'callbacks',
        'tickets', 'urgent', 'directory', 'appraisals',
    ];

    public function up(): void
    {
        foreach (DB::table('roles')->where('name', '!=', 'admin')->get() as $role) {
            $perms = array_values(array_unique(array_merge(
                json_decode($role->nav_permissions ?? '[]', true) ?? [],
                $this->coreKeys,
            )));
            DB::table('roles')->where('id', $role->id)->update(['nav_permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->where('role', '!=', 'admin')->get() as $user) {
            $perms = array_values(array_unique(array_merge(
                json_decode($user->nav_permissions ?? '[]', true) ?? [],
                $this->coreKeys,
            )));
            DB::table('users')->where('id', $user->id)->update(['nav_permissions' => json_encode($perms)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('roles')->where('name', '!=', 'admin')->get() as $role) {
            $perms = array_values(array_diff(json_decode($role->nav_permissions ?? '[]', true) ?? [], $this->coreKeys));
            DB::table('roles')->where('id', $role->id)->update(['nav_permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->where('role', '!=', 'admin')->get() as $user) {
            $perms = array_values(array_diff(json_decode($user->nav_permissions ?? '[]', true) ?? [], $this->coreKeys));
            DB::table('users')->where('id', $user->id)->update(['nav_permissions' => json_encode($perms)]);
        }
    }
};
