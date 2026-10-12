<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Depreciation is now entered as a rate (% per year). The rate and the
// useful life are the same straight-line figure (life = 100 ÷ rate), so the
// life is kept as the stored value but must allow fractions — e.g. 15%/yr is
// a 6.666667-year life (6 decimals so annual depreciation comes out exact).
// Existing whole-year values are unchanged.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->decimal('useful_life_years', 10, 6)->nullable()->change();
        });
        Schema::table('fixed_asset_revaluations', function (Blueprint $table) {
            $table->decimal('new_useful_life_years', 10, 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->unsignedSmallInteger('useful_life_years')->nullable()->change();
        });
        Schema::table('fixed_asset_revaluations', function (Blueprint $table) {
            $table->unsignedSmallInteger('new_useful_life_years')->nullable()->change();
        });
    }
};
