<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('call_source')->nullable()->after('description');
            $table->string('call_destination')->nullable()->after('call_source');
            $table->string('radio_channel')->nullable()->after('referred_to');
            $table->text('services_requested_before')->nullable()->after('services_requested');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['call_source', 'call_destination', 'radio_channel', 'services_requested_before']);
        });
    }
};
