<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'idx_tickets_dash_period'   => ['deleted_at', 'created_at'],
        'idx_tickets_dash_status'   => ['deleted_at', 'status'],
        'idx_tickets_dash_province' => ['deleted_at', 'province'],
        'idx_tickets_dash_mode'     => ['deleted_at', 'mode_of_communication'],
        'idx_tickets_dash_purpose'  => ['deleted_at', 'purpose_of_call'],
        'idx_tickets_dash_validity' => ['deleted_at', 'call_validity'],
        'idx_tickets_dash_priority' => ['deleted_at', 'priority'],
        'idx_tickets_dash_referral' => ['deleted_at', 'referred_to'],
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
        });
    }
};
