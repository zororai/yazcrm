<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 2026_07_10_000001 migration added composite indexes led by `deleted_at`.
 * Since `deleted_at` is NULL for nearly every row, MySQL's optimizer can't
 * distinguish these from the other deleted_at-prefixed dashboard indexes and
 * often picks the wrong one (full-ish scan instead of the selective filter
 * column). Re-creating them with the filter column leading fixes plan
 * selection — verified ~19x faster (290ms -> 15ms) on a project filter.
 */
return new class extends Migration
{
    private array $indexes = [
        'idx_tickets_dash_project' => ['project', 'deleted_at'],
        'idx_tickets_dash_gender'  => ['caller_gender', 'deleted_at'],
        'idx_tickets_dash_age'     => ['caller_age', 'deleted_at'],
    ];

    public function up(): void
    {
        $existing = collect(DB::select("SHOW INDEX FROM `tickets`"))->pluck('Key_name')->unique();

        foreach ($this->indexes as $name => $cols) {
            if ($existing->contains($name)) {
                DB::statement("ALTER TABLE `tickets` DROP INDEX `{$name}`");
            }
            $colList = collect($cols)->map(fn ($c) => "`{$c}`")->implode(', ');
            DB::statement("ALTER TABLE `tickets` ADD INDEX `{$name}` ({$colList})");
        }

        if ($existing->contains('idx_tickets_dash_service')) {
            DB::statement("ALTER TABLE `tickets` DROP INDEX `idx_tickets_dash_service`");
        }
        DB::statement("ALTER TABLE `tickets` ADD INDEX `idx_tickets_dash_service` (`services_requested`(191), `deleted_at`)");
    }

    public function down(): void
    {
        $existing = collect(DB::select("SHOW INDEX FROM `tickets`"))->pluck('Key_name')->unique();

        foreach (array_keys($this->indexes) as $name) {
            if ($existing->contains($name)) {
                DB::statement("ALTER TABLE `tickets` DROP INDEX `{$name}`");
            }
        }
        if ($existing->contains('idx_tickets_dash_service')) {
            DB::statement("ALTER TABLE `tickets` DROP INDEX `idx_tickets_dash_service`");
        }
    }
};
