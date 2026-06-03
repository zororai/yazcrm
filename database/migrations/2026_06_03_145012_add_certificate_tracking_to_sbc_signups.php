<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sbc_signups', function (Blueprint $table) {
            $table->string('certificate_status')->nullable()->after('synced_at');
            $table->timestamp('certificate_downloaded_at')->nullable()->after('certificate_status');
        });
    }

    public function down(): void
    {
        Schema::table('sbc_signups', function (Blueprint $table) {
            $table->dropColumn(['certificate_status', 'certificate_downloaded_at']);
        });
    }
};
