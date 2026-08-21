<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_collection_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('version_label')->nullable();
            $table->json('schema');
            $table->string('status')->default('draft'); // draft|published|retired
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['form_id', 'version_number']);
            $table->index(['form_id', 'status']);
        });

        Schema::table('data_collection_forms', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')->on('data_collection_form_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('data_collection_forms', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('data_collection_form_versions');
    }
};
