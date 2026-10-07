<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// LSJP (Livelihood Skills & Job Preparation) register: people trained in a
// skill, followed up 1, 3 and 6 months after training.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsjp_participants', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('id_number')->nullable();          // national ID
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('sex')->nullable();                // male|female|other
            $table->string('phone')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('location')->nullable();           // village / ward / area
            $table->string('key_population')->nullable();     // from Key Pops lookup
            $table->string('current_activity')->nullable();   // current business or activity
            $table->string('skill_trained')->nullable();
            $table->string('project')->nullable();            // from Project lookup
            $table->date('training_completed_on');            // check-ups are due 1/3/6 months after this
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('training_completed_on');
            $table->index('id_number');
        });

        Schema::create('lsjp_checkups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lsjp_participant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');             // 1, 3 or 6
            $table->date('conducted_on');
            $table->string('progress');                       // see LsjpCheckup::PROGRESS
            $table->string('activity_status')->nullable();    // what they are doing now
            $table->text('challenges')->nullable();
            $table->text('comment')->nullable();
            $table->string('referred_to')->nullable();
            $table->text('referral_notes')->nullable();
            $table->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lsjp_participant_id', 'month']);
        });

        Schema::create('lsjp_checkup_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lsjp_checkup_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsjp_checkup_photos');
        Schema::dropIfExists('lsjp_checkups');
        Schema::dropIfExists('lsjp_participants');
    }
};
