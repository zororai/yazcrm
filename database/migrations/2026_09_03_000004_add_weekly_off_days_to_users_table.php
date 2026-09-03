<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Weekday numbers (0=Sun..6=Sat) this agent is always off,
            // e.g. [0, 6] for weekends. Per-agent, unlike the one-off
            // special_days table.
            $table->json('weekly_off_days')->nullable()->after('profile_prompt_dismiss_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('weekly_off_days');
        });
    }
};
