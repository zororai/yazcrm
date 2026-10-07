<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Success stories (from the Success Stories module) attached to a monthly
// progress report. A staff member can only attach their own stories.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_report_success_story', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('success_story_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['progress_report_id', 'success_story_id'], 'progress_report_story_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_report_success_story');
    }
};
