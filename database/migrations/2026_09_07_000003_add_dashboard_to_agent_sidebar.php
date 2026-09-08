<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Adds Dashboard back to the agent role's sidebar (shows their own
// activity — DashboardController already scopes non-managers to their own
// extension's stats). Additive on top of the restricted list set in
// 2026_09_07_000002.
return new class extends Migration
{
    public function up(): void
    {
        $this->addPerm('roles', 'name');
        $this->addPerm('users', 'role');
    }

    private function addPerm(string $table, string $roleColumn): void
    {
        DB::table($table)->where($roleColumn, 'agent')
            ->orderBy('id')
            ->get(['id', 'nav_permissions'])
            ->each(function ($row) use ($table) {
                $perms = json_decode($row->nav_permissions ?? '[]', true) ?? [];
                if (! in_array('dashboard', $perms, true)) {
                    $perms[] = 'dashboard';
                }
                DB::table($table)->where('id', $row->id)->update([
                    'nav_permissions' => json_encode(array_values($perms)),
                ]);
            });
    }

    public function down(): void
    {
        // Additive only — not reversed.
    }
};
