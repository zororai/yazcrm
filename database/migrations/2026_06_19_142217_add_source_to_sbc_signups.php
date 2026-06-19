<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sbc_signups', function (Blueprint $table) {
            // 'chatbot' = synced from Google Sheets/chatbot, 'import' = manually imported CSV/Excel
            $table->string('source', 20)->default('chatbot')->after('sheet');
        });
    }

    public function down(): void
    {
        Schema::table('sbc_signups', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
