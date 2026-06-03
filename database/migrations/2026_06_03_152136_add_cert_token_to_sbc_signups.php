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
            $table->string('cert_token', 64)->nullable()->unique()->after('whatsapp_sent_at');
            $table->unsignedTinyInteger('cert_download_count')->default(0)->after('cert_token');
        });
    }

    public function down(): void
    {
        Schema::table('sbc_signups', function (Blueprint $table) {
            $table->dropColumn(['cert_token', 'cert_download_count']);
        });
    }
};
