<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_number');
            $table->string('to_number');
            $table->text('body');
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->string('message_sid')->nullable()->unique();
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->string('media_url')->nullable();
            $table->timestamps();

            $table->index('from_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
