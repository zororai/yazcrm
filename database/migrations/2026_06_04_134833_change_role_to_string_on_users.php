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
        // Change from ENUM('admin','agent') to VARCHAR so custom roles can be stored
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            Schema::table('users', fn (Blueprint $table) => $table->string('role', 100)->default('agent')->change());

            return;
        }

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE users MODIFY COLUMN `role` VARCHAR(100) NOT NULL DEFAULT 'agent'"
        );
    }

    public function down(): void
    {
        // Revert — note: any rows with roles other than admin/agent will be truncated
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            Schema::table('users', fn (Blueprint $table) => $table->enum('role', ['admin', 'agent'])->default('agent')->change());

            return;
        }

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE users MODIFY COLUMN `role` ENUM('admin','agent') NOT NULL DEFAULT 'agent'"
        );
    }
};
