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
            $table->timestamp('whatsapp_sent_at')->nullable()->after('certificate_downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('sbc_signups', function (Blueprint $table) {
            $table->dropColumn('whatsapp_sent_at');
        });
    }
};
