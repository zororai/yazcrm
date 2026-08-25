<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();

            $table->string('maintenance_type'); // routine_service|repair|inspection|calibration|cleaning|upgrade|preventive|corrective
            $table->text('description')->nullable();
            $table->string('service_provider')->nullable();
            $table->date('service_date');
            $table->decimal('cost', 12, 2)->nullable();
            $table->date('next_service_date')->nullable();
            $table->string('status')->default('scheduled'); // scheduled|completed|cancelled
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['fixed_asset_id', 'status'], 'fa_maintenance_asset_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_maintenance');
    }
};
