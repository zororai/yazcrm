<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_attendances', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique(); // generated on the Android device, dedup key on resync
            $table->foreignId('beneficiary_activity_id')->constrained('beneficiary_activities')->cascadeOnDelete();
            $table->string('full_name');
            $table->enum('sex', ['M', 'F']);
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('district')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('id_number_or_dob')->nullable();
            $table->string('signature_path')->nullable(); // optional photo of signature, stored on sync
            $table->timestamp('captured_at')->nullable(); // when captured on the device (may be offline, so != created_at)
            $table->timestamps();

            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_attendances');
    }
};
