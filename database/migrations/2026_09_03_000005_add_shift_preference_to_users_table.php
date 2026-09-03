<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // null/'rotating' = alternates Day/Night as normal; 'day' or
            // 'night' pins this agent to that shift only during their
            // working days.
            $table->string('shift_preference')->nullable()->after('weekly_off_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('shift_preference');
        });
    }
};
