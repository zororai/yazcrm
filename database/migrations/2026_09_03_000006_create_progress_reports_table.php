<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('month'); // stored as the 1st of the reporting month
            $table->string('job_title')->nullable();
            $table->string('supervisor')->nullable();
            $table->date('date_submitted')->nullable();
            $table->text('overall_progress')->nullable();
            $table->json('activities')->nullable(); // [{activity, completed, details}]
            $table->timestamps();

            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_reports');
    }
};
