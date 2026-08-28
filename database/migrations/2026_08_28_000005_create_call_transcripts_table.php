<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('calls')->cascadeOnDelete();

            $table->string('language')->nullable();
            $table->string('model')->nullable();
            $table->longText('transcript')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('error_message')->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique('call_id');
            $table->index(['status', 'created_at'], 'call_transcripts_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_transcripts');
    }
};
