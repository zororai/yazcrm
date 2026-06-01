<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('immediate_action_required')->default(false)->nullable()->change();
            $table->boolean('is_repeat_caller')->default(false)->nullable()->change();
            $table->boolean('uptake_confirmed')->default(false)->nullable()->change();
        });
    }

    public function down(): void
    {
        // intentionally left blank — reverting these defaults is safe to skip
    }
};
