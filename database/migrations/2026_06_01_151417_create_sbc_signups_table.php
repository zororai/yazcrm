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
        Schema::create('sbc_signups', function (Blueprint $table) {
            $table->id();
            $table->string('sheet', 50);          // 'SBC Signups' or 'Certificates To Process'
            $table->date('date')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('surname', 100)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('sex', 20)->nullable();
            $table->string('location', 150)->nullable();
            $table->timestamp('synced_at')->nullable(); // last pulled from Sheets
            $table->timestamps();

            // Prevent duplicate rows from repeat syncs
            $table->unique(['sheet', 'date', 'phone_number'], 'sbc_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sbc_signups');
    }
};
