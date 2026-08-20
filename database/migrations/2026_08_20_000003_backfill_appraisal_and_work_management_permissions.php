<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Appraisals and Work Management should be visible to every authenticated
    // user (agents included), not just roles that were manually toggled on.
    private array $keys = ['appraisals', 'appraisal_reviews', 'work_management'];

    public function up(): void
    {
        foreach (DB::table('roles')->where('name', '!=', 'admin')->get() as $role) {
            $perms = array_values(array_unique(array_merge(
                json_decode($role->nav_permissions ?? '[]', true) ?? [],
                $this->keys,
            )));
            DB::table('roles')->where('id', $role->id)->update(['nav_permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->where('role', '!=', 'admin')->get() as $user) {
            $perms = array_values(array_unique(array_merge(
                json_decode($user->nav_permissions ?? '[]', true) ?? [],
                $this->keys,
            )));
            DB::table('users')->where('id', $user->id)->update(['nav_permissions' => json_encode($perms)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('roles')->where('name', '!=', 'admin')->get() as $role) {
            $perms = array_values(array_diff(json_decode($role->nav_permissions ?? '[]', true) ?? [], $this->keys));
            DB::table('roles')->where('id', $role->id)->update(['nav_permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->where('role', '!=', 'admin')->get() as $user) {
            $perms = array_values(array_diff(json_decode($user->nav_permissions ?? '[]', true) ?? [], $this->keys));
            DB::table('users')->where('id', $user->id)->update(['nav_permissions' => json_encode($perms)]);
        }
    }
};
