<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('calls')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_url')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('format')->default('wav');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
