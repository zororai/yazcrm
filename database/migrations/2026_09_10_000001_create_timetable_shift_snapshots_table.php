<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Backs the "Undo Last Generate" button: each time a manager generates a
// timetable, whatever shifts existed before (for the affected agents/range)
// are snapshotted here so the action can be reverted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_shift_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->json('agent_ids');
            $table->json('old_shifts');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_shift_snapshots');
    }
};
