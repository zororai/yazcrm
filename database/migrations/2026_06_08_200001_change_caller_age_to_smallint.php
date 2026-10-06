<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('tickets', fn (Blueprint $table) => $table->unsignedSmallInteger('caller_age')->nullable()->change());

            return;
        }

        DB::statement('ALTER TABLE tickets MODIFY COLUMN caller_age SMALLINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('tickets', fn (Blueprint $table) => $table->unsignedTinyInteger('caller_age')->nullable()->change());

            return;
        }

        DB::statement('ALTER TABLE tickets MODIFY COLUMN caller_age TINYINT UNSIGNED NULL');
    }
};
