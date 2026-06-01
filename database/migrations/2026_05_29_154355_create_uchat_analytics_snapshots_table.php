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
        Schema::create('uchat_analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();            // one row per calendar day
            $table->unsignedInteger('total_bot_users')->default(0);
            $table->unsignedInteger('new_bot_users')->default(0);
            $table->unsignedInteger('active_today')->default(0);
            $table->json('channel_counts')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uchat_analytics_snapshots');
    }
};
