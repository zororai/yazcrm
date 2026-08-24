<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Stock Transfers, Stocktakes, and Item Categories used to ride on the
    // single 'stores' permission. Anyone who already had 'stores' keeps
    // access to all three now that they're independently toggleable.
    private array $keys = ['stock_transfers', 'stocktakes', 'item_categories'];

    public function up(): void
    {
        foreach (DB::table('roles')->where('name', '!=', 'admin')->get() as $role) {
            $perms = json_decode($role->nav_permissions ?? '[]', true) ?? [];
            if (! in_array('stores', $perms, true)) {
                continue;
            }
            $perms = array_values(array_unique(array_merge($perms, $this->keys)));
            DB::table('roles')->where('id', $role->id)->update(['nav_permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->where('role', '!=', 'admin')->get() as $user) {
            $perms = json_decode($user->nav_permissions ?? '[]', true) ?? [];
            if (! in_array('stores', $perms, true)) {
                continue;
            }
            $perms = array_values(array_unique(array_merge($perms, $this->keys)));
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
