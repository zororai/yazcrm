<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // "Counselors" = users with role 'agent'. Grants every existing
    // nav_permissions key (every sidebar section) to the 'agent' role
    // template and to all current agent users — full sidebar visibility,
    // but role stays 'agent' so existing data-scoping (e.g. agents only see
    // calls/recordings tied to their own extension) is unaffected.
    private array $allPerms = [
        'dashboard', 'helpline_dashboard', 'dialer', 'calls', 'recordings', 'callbacks', 'tickets',
        'urgent', 'directory', 'appraisals', 'appraisal_reviews', 'appraisal_archive', 'activity_reports',
        'work_management', 'stores', 'stock_transfers', 'stocktakes', 'item_categories', 'fixed_assets',
        'procurement', 'data_collection', 'extensions', 'analytics', 'targets', 'by_project',
        'domains', 'bot_contacts', 'users', 'yeastar', 'yalep', 'registry', 'risk', 'sbc', 'roles',
    ];

    public function up(): void
    {
        DB::table('roles')->where('name', 'agent')->update([
            'nav_permissions' => json_encode($this->allPerms),
        ]);

        DB::table('users')->where('role', 'agent')->update([
            'nav_permissions' => json_encode($this->allPerms),
        ]);
    }

    public function down(): void
    {
        // Not reversible to the exact prior per-user state (this migration
        // overwrites rather than diffs, and prior values varied per user/
        // role customization) — rolling back clears the grant rather than
        // guessing at what each row held before.
        DB::table('roles')->where('name', 'agent')->update(['nav_permissions' => json_encode([])]);
        DB::table('users')->where('role', 'agent')->update(['nav_permissions' => json_encode([])]);
    }
};
