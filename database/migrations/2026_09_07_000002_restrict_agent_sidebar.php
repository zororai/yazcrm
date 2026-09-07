<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The 'agent' role previously had every sidebar section granted (see
// 2026_09_01_000002 and 2026_09_07_000001). Per explicit instruction, the
// agent sidebar is now locked down to exactly this list — replaces (not
// merges into) both the role template and every existing agent user's
// nav_permissions.
return new class extends Migration
{
    private array $agentPerms = [
        'calls', 'my_work', 'recordings', 'tickets', 'urgent', 'directory',
        'appraisals', 'activity_reports', 'timetable', 'progress_reports', 'success_stories',
    ];

    public function up(): void
    {
        DB::table('roles')->where('name', 'agent')->update([
            'nav_permissions' => json_encode($this->agentPerms),
        ]);

        DB::table('users')->where('role', 'agent')->update([
            'nav_permissions' => json_encode($this->agentPerms),
        ]);
    }

    public function down(): void
    {
        // Prior state (varying per-row grants from earlier migrations) isn't
        // reconstructible — down() intentionally leaves the restricted set in
        // place rather than guessing.
    }
};
