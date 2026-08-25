<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('inspector_id')->constrained('users')->cascadeOnDelete();

            $table->date('inspected_at');
            $table->string('condition'); // new|excellent|good|fair|poor|damaged|unserviceable
            $table->string('working_status')->default('working'); // working|not_working|partially_working
            $table->text('damage_notes')->nullable();
            $table->text('comments')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['fixed_asset_id', 'inspected_at'], 'fa_inspections_asset_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_inspections');
    }
};
