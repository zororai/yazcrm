<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_collection_form_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('data_collection_form_versions')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('assigned'); // assigned|in_progress|completed|expired|cancelled
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['form_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_collection_form_assignments');
    }
};
