<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Anyone who already manages Fixed Assets gets Procurement (Suppliers &
    // Purchase Orders) too, so nobody loses reach when this new module ships.
    private string $key = 'procurement';

    public function up(): void
    {
        foreach (DB::table('roles')->where('name', '!=', 'admin')->get() as $role) {
            $perms = json_decode($role->nav_permissions ?? '[]', true) ?? [];
            if (! in_array('fixed_assets', $perms, true)) {
                continue;
            }
            $perms = array_values(array_unique(array_merge($perms, [$this->key])));
            DB::table('roles')->where('id', $role->id)->update(['nav_permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->where('role', '!=', 'admin')->get() as $user) {
            $perms = json_decode($user->nav_permissions ?? '[]', true) ?? [];
            if (! in_array('fixed_assets', $perms, true)) {
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
