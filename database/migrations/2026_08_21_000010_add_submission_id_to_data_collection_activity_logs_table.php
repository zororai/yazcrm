<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_collection_activity_logs', function (Blueprint $table) {
            $table->foreignId('submission_id')->nullable()->after('form_version_id')
                ->constrained('data_collection_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('data_collection_activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submission_id');
        });
    }
};
