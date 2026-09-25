<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->unsignedSmallInteger('useful_life_years')->nullable()->after('purchase_cost');
            $table->decimal('salvage_value', 12, 2)->default(0)->after('useful_life_years');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['useful_life_years', 'salvage_value']);
        });
    }
};
