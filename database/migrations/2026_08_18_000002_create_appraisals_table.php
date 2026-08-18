<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('employee_number')->nullable();
            $table->date('start_date')->nullable();

            $table->string('reviewer_name')->nullable();
            $table->date('date_of_review')->nullable();
            $table->date('next_review_date')->nullable();

            $table->unsignedTinyInteger('overall_rating')->nullable();

            $table->json('employee_responses')->nullable();
            $table->json('supervisor_responses')->nullable();

            $table->enum('status', ['draft', 'submitted', 'completed'])->default('draft');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('employee_signed_at')->nullable();
            $table->timestamp('supervisor_signed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['supervisor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisals');
    }
};
