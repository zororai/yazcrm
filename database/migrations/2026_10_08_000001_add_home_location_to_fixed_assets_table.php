<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Two locations per fixed asset:
//  - home_location_id: "Asset location" — where the asset is normally kept.
//  - location_id (existing): "Issued location" — where it is issued and in use
//    now; Assign/Transfer keep updating this one.
// Existing assets start with their current location as their home location.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->foreignId('home_location_id')->nullable()->after('location_id')
                ->constrained('locations')->nullOnDelete();
        });

        DB::table('fixed_assets')->whereNull('home_location_id')->whereNotNull('location_id')
            ->update(['home_location_id' => DB::raw('location_id')]);
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_location_id');
        });
    }
};
