<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('success_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the agent who wrote it
            $table->foreignId('recording_id')->nullable()->constrained()->nullOnDelete(); // optional, from the ticket's call
            $table->string('title');
            $table->text('story');
            $table->string('status')->default('pending'); // pending | approved | needs_revision
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('success_story_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('success_story_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // storage/app/public path
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('success_story_photos');
        Schema::dropIfExists('success_stories');
    }
};
