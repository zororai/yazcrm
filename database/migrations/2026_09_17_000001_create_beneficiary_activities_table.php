<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique(); // generated on the Android device
            $table->string('activity_name');
            $table->date('activity_date');
            $table->string('compiled_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_activities');
    }
};
