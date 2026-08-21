<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_collection_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('data_collection_projects')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft|published|unpublished|archived
            $table->foreignId('current_version_id')->nullable(); // FK added after form_versions table exists
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_collection_forms');
    }
};
