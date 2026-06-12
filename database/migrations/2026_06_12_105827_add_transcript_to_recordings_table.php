<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->longText('transcript')->nullable()->after('format');
            $table->text('ai_notes')->nullable()->after('transcript');
            $table->enum('transcription_status', ['pending', 'processing', 'done', 'failed'])
                  ->default('pending')->after('ai_notes');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['transcript', 'ai_notes', 'transcription_status']);
        });
    }
};
