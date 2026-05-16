<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extensions', function (Blueprint $table) {
            $table->id();
            $table->string('extension_number')->unique();
            $table->string('name');
            $table->enum('status', ['registered', 'unregistered', 'ringing', 'on_call', 'idle'])->default('idle');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('caller_id_name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('voicemail_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};
