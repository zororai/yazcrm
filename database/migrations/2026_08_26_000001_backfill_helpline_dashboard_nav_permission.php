<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Anyone who already had the main 'dashboard' gets the new Helpline
    // Dashboards and Data link too, so nobody loses reach when it ships.
    private string $key = 'helpline_dashboard';

    public function up(): void
    {
        foreach (DB::table('roles')->where('name', '!=', 'admin')->get() as $role) {
            $perms = json_decode($role->nav_permissions ?? '[]', true) ?? [];
            if (! in_array('dashboard', $perms, true)) {
                continue;
            }
            $perms = array_values(array_unique(array_merge($perms, [$this->key])));
            DB::table('roles')->where('id', $role->id)->update(['nav_permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->where('role', '!=', 'admin')->get() as $user) {
            $perms = json_decode($user->nav_permissions ?? '[]', true) ?? [];
            if (! in_array('dashboard', $perms, true)) {
                continue;
            }
            $perms = array_values(array_unique(array_merge($perms, [$this->key])));
            DB::table('users')->where('id', $user->id)->update(['nav_permissions' => json_encode($perms)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('roles')->where('name', '!=', 'admin')->get() as $role) {
            $perms = array_values(array_diff(json_decode($role->nav_permissions ?? '[]', true) ?? [], [$this->key]));
            DB::table('roles')->where('id', $role->id)->update(['nav_permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->where('role', '!=', 'admin')->get() as $user) {
            $perms = array_values(array_diff(json_decode($user->nav_permissions ?? '[]', true) ?? [], [$this->key]));
            DB::table('users')->where('id', $user->id)->update(['nav_permissions' => json_encode($perms)]);
        }
    }
};
