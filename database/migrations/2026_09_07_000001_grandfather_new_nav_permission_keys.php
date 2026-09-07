<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A handful of sidebar sections (My Work, Audit Trail, IT Asset Categories,
// Transcription Test Tool, Counsellor Profiles, Team Reports, Timetable,
// Progress Report, Success Stories) were previously unconditional or
// hardcoded to isAdmin/role checks — not driven by nav_permissions at all.
// They're now real togglable permissions. Without this migration, every
// existing role/user that didn't have these keys would silently lose
// access to sections they could see a moment ago. This grants the new
// keys everywhere the corresponding old behavior already allowed access,
// so nothing regresses — Roles/Users management can narrow it back down
// from here going forward.
return new class extends Migration
{
    // My Work, Timetable, Progress Report, Success Stories were visible to
    // every logged-in user — grant to everyone. The rest mirror what was
    // already hardcoded (admin always; director/helpline_manager for the
    // counsellor-facing ones).
    private array $grantToEveryone = ['my_work', 'timetable', 'progress_reports', 'success_stories'];
    private array $grantToManagers = ['counsellor_profiles', 'team_reports'];
    private array $adminOnlyKeys   = ['audit_trail', 'it_asset_categories', 'transcription_test'];

    public function up(): void
    {
        $this->grantKeys('roles', array_merge($this->grantToEveryone, $this->grantToManagers, $this->adminOnlyKeys));
        $this->grantKeys('users', array_merge($this->grantToEveryone, $this->grantToManagers, $this->adminOnlyKeys));
    }

    private function grantKeys(string $table, array $allKeys): void
    {
        DB::table($table)->select('id', 'nav_permissions', ...($table === 'users' ? ['role'] : ['name']))
            ->orderBy('id')
            ->get()
            ->each(function ($row) use ($table, $allKeys) {
                $roleName = $table === 'users' ? $row->role : $row->name;

                // Admins already get full access elsewhere (nav_permissions
                // is bypassed for them) — nothing to grant.
                if ($roleName === 'admin') {
                    return;
                }

                $keysForThisRow = $this->grantToEveryone;
                if (in_array($roleName, ['director', 'helpline_manager'], true)) {
                    $keysForThisRow = array_merge($keysForThisRow, $this->grantToManagers, $this->adminOnlyKeys);
                }
                // Agents previously had every existing perm key granted
                // (see the 2026_09_01 migration) — extend that the same way.
                if ($roleName === 'agent') {
                    $keysForThisRow = array_merge($keysForThisRow, $allKeys);
                }

                $existing = json_decode($row->nav_permissions ?? '[]', true) ?? [];
                $updated  = array_values(array_unique(array_merge($existing, $keysForThisRow)));

                DB::table($table)->where('id', $row->id)->update([
                    'nav_permissions' => json_encode($updated),
                ]);
            });
    }

    public function down(): void
    {
        // Not meaningfully reversible — this only ever adds keys on top of
        // whatever each row already had, never removes or overwrites.
    }
};
