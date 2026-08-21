<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_collection_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_uid')->unique();
            $table->foreignId('project_id')->constrained('data_collection_projects')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('data_collection_form_versions')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('data_collection_form_assignments')->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();

            $table->string('status')->default('draft'); // draft|submitted|under_review|approved|rejected|correction_required|cancelled
            $table->json('answers')->nullable();
            $table->unsignedTinyInteger('completion_percentage')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_id', 'status']);
            $table->index(['submitted_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_collection_submissions');
    }
};
