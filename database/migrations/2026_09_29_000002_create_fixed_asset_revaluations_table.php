<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_revaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revalued_by')->constrained('users')->cascadeOnDelete();
            $table->date('revaluation_date');
            $table->decimal('previous_value', 12, 2)->nullable();
            $table->decimal('revalued_amount', 12, 2);
            $table->unsignedSmallInteger('new_useful_life_years')->nullable();
            $table->decimal('new_salvage_value', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['fixed_asset_id', 'revaluation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_revaluations');
    }
};
