<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Locations never had a real use for a short "code" — nothing elsewhere in
// the app reads it, and the table has no rows yet. Dropped rather than kept
// as dead UI, matching the removal from the form/controller/model.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id');
        });
    }
};
