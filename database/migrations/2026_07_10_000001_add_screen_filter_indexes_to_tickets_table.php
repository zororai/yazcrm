<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'idx_tickets_dash_project' => ['deleted_at', 'project'],
        'idx_tickets_dash_gender'  => ['deleted_at', 'caller_gender'],
        'idx_tickets_dash_age'     => ['deleted_at', 'caller_age'],
    ];

    public function up(): void
    {
        $existing = collect(DB::select("SHOW INDEX FROM `tickets`"))->pluck('Key_name')->unique();

        Schema::table('tickets', function (Blueprint $table) use ($existing) {
            foreach ($this->indexes as $name => $cols) {
                if (!$existing->contains($name)) {
                    $table->index($cols, $name);
                }
            }
        });

        // services_requested is TEXT — needs an explicit prefix length for indexing
        if (!$existing->contains('idx_tickets_dash_service')) {
            DB::statement('ALTER TABLE `tickets` ADD INDEX `idx_tickets_dash_service` (`deleted_at`, `services_requested`(191))');
        }
    }

    public function down(): void
    {
        $existing = collect(DB::select("SHOW INDEX FROM `tickets`"))->pluck('Key_name')->unique();

        Schema::table('tickets', function (Blueprint $table) use ($existing) {
            foreach (array_keys($this->indexes) as $name) {
                if ($existing->contains($name)) {
                    $table->dropIndex($name);
                }
            }
            if ($existing->contains('idx_tickets_dash_service')) {
                $table->dropIndex('idx_tickets_dash_service');
            }
        });
    }
};
