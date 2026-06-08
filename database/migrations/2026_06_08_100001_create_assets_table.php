<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->string('name');
            $table->enum('type', ['server', 'network', 'endpoint', 'telephony', 'application', 'power', 'saas']);
            $table->string('location')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('owner')->nullable();
            $table->string('os_version')->nullable();
            $table->enum('patch_status', ['current', 'outdated', 'eol', 'unknown'])->default('unknown');
            $table->enum('data_sensitivity', ['none', 'internal', 'sensitive'])->default('internal');
            $table->enum('criticality_393', ['low', 'medium', 'high'])->default('medium');
            $table->enum('source', ['scan', 'manual'])->default('manual');
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('supplier')->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->enum('lifecycle_status', ['active', 'in_repair', 'retired', 'disposed'])->default('active');
            $table->date('replace_due_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
