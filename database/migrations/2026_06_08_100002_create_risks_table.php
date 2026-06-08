<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('risk_ref')->unique();
            $table->enum('category', ['infrastructure', 'software', 'data_protection', 'cybersecurity', 'continuity', 'people_process']);
            $table->text('description');
            $table->text('cause')->nullable();
            $table->tinyInteger('likelihood')->default(1);
            $table->tinyInteger('impact')->default(1);
            $table->tinyInteger('inherent_score')->default(1);
            $table->tinyInteger('residual_score')->nullable();
            $table->string('risk_owner')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};
