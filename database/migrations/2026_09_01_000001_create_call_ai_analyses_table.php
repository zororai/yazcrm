<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('calls')->cascadeOnDelete();

            // Raw AI output — never mutated after generation. Distinct from
            // reviewed_summary so the record always shows what the AI said
            // vs. what a human confirmed (spec §19).
            $table->text('ai_summary')->nullable();
            $table->string('ai_category')->nullable();
            $table->string('ai_priority')->nullable();
            $table->boolean('ai_follow_up_required')->default(false);
            $table->boolean('ai_referral_required')->default(false);
            $table->string('ai_model')->nullable();

            $table->enum('status', ['pending_review', 'accepted', 'edited', 'rejected'])->default('pending_review');
            $table->text('reviewed_summary')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->string('analysis_status')->default('pending'); // pending, completed, failed
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique('call_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_ai_analyses');
    }
};
