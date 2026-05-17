<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('daily_target');
            $table->date('start_date');  // carry-forward counted from this date
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_targets');
    }
};
