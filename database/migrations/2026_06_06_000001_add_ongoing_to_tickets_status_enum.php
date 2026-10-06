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
            Schema::table('tickets', fn (Blueprint $table) => $table->enum('status', ['open', 'in_progress', 'ongoing', 'resolved', 'closed'])->default('open')->change());

            return;
        }

        DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM('open','in_progress','ongoing','resolved','closed') NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('tickets', fn (Blueprint $table) => $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open')->change());

            return;
        }

        DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open'");
    }
};
