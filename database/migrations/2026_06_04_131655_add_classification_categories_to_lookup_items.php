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
        Schema::table('lookup_items', function (Blueprint $table) {
            // Stores which classification categories apply when this service is selected
            $table->json('classification_categories')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('lookup_items', function (Blueprint $table) {
            $table->dropColumn('classification_categories');
        });
    }
};
