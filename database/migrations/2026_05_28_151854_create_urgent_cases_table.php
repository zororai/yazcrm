<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urgent_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('subject');
            $table->string('contact_number', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open'); // open | resolved
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_id')->nullable();
            $table->unsignedBigInteger('source_ticket_id')->nullable();  // ticket that triggered this
            $table->unsignedBigInteger('created_ticket_id')->nullable(); // ticket created to resolve this
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urgent_cases');
    }
};
