<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// When a service dropdown is set to "Other (Specify):" the agent types what the
// service was — kept in its own column so the service itself still groups as
// "Other" on the dashboards.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('services_requested_before_other')->nullable()->after('services_requested_before');
            $table->string('services_requested_other')->nullable()->after('services_requested');
            $table->string('second_service_requested_other')->nullable()->after('second_service_requested');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['services_requested_before_other', 'services_requested_other', 'second_service_requested_other']);
        });
    }
};
