<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compiled_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('viewer_ids')->nullable();

            $table->string('name_of_activity')->nullable();
            $table->date('date')->nullable();
            $table->string('district')->nullable();
            $table->string('organized_by')->nullable();
            $table->string('officer_in_charge')->nullable();
            $table->string('venue')->nullable();

            $table->json('attendance')->nullable();

            $table->text('objectives')->nullable();
            $table->text('methodology')->nullable();
            $table->text('narration')->nullable();
            $table->text('key_outcomes')->nullable();
            $table->text('challenges')->nullable();

            $table->json('action_items')->nullable();

            $table->string('pictures_link')->nullable();
            $table->json('impact_quotes')->nullable();

            $table->enum('status', ['draft', 'submitted', 'reviewed', 'approved'])->default('draft');

            $table->timestamp('compiled_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['compiled_by', 'status']);
            $table->index(['reviewer_id', 'status']);
            $table->index(['approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_reports');
    }
};
