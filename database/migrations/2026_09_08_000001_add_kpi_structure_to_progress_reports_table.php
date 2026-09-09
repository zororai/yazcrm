<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Restructures the report to match the org's real "Individual Monthly
// Progress Report" template: numbered KPI sections (each with its own
// narrative), a province/clients-reached table, a male/female client
// breakdown, a services-requested table, and a Success Stories section —
// on top of the existing workplan-activities table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->json('kpis')->nullable()->after('overall_progress'); // [{title, description}]
            $table->json('provinces')->nullable()->after('kpis'); // [{province, clients}]
            $table->unsignedInteger('male_clients')->nullable()->after('provinces');
            $table->unsignedInteger('female_clients')->nullable()->after('male_clients');
            $table->json('services')->nullable()->after('female_clients'); // [{service, clients}]
            $table->json('success_stories')->nullable()->after('activities'); // [{challenge, solution}]
        });
    }

    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropColumn(['kpis', 'provinces', 'male_clients', 'female_clients', 'services', 'success_stories']);
        });
    }
};
