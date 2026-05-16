<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->string('call_id')->unique();
            $table->string('caller');
            $table->string('callee');
            $table->enum('direction', ['inbound', 'outbound', 'internal'])->default('inbound');
            $table->enum('status', ['answered', 'missed', 'busy', 'failed', 'voicemail'])->default('missed');
            $table->unsignedInteger('duration')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('recording_file')->nullable();
            $table->string('extension_number')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['direction', 'status']);
            $table->index('started_at');
            $table->index('caller');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
