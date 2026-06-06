<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM('open','in_progress','ongoing','resolved','closed') NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open'");
    }
};
