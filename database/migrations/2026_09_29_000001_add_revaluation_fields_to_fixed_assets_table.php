<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->unsignedSmallInteger('revaluation_cycle_years')->default(3)->after('salvage_value');
            $table->date('last_revalued_at')->nullable()->after('revaluation_cycle_years');
            $table->decimal('current_value', 12, 2)->nullable()->after('last_revalued_at');
            $table->date('depreciation_base_date')->nullable()->after('current_value');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['revaluation_cycle_years', 'last_revalued_at', 'current_value', 'depreciation_base_date']);
        });
    }
};
