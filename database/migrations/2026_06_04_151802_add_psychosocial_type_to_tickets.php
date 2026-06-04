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
            $table->string('psychosocial_type')->nullable()->after('classification');
        });

        // Backfill from existing classification JSON for any already-saved tickets
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE tickets SET psychosocial_type = JSON_UNQUOTE(JSON_EXTRACT(classification, '$.psychosocial_type'))
             WHERE classification IS NOT NULL
               AND JSON_UNQUOTE(JSON_EXTRACT(classification, '$.psychosocial_type')) IS NOT NULL
               AND JSON_UNQUOTE(JSON_EXTRACT(classification, '$.psychosocial_type')) != ''
               AND JSON_UNQUOTE(JSON_EXTRACT(classification, '$.psychosocial_type')) != 'null'"
        );
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('psychosocial_type');
        });
    }
};
