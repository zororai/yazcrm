<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Youth Enterprise Graduate Model: Beneficiary Profiling Tool. One row per
// paper form. Single-choice answers are short string keys (e.g. 'low',
// 'self_employed'); tick-all-that-apply answers are JSON arrays of keys.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique(); // generated on the device, dedup key on resync
            $table->boolean('consent_given')->default(false); // introduction consent

            // Basic information
            $table->string('full_name');
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 10)->nullable(); // F, M, Other
            $table->string('location')->nullable(); // district/ward/village
            $table->string('phone_number')->nullable();
            $table->string('alternative_contact')->nullable();
            $table->string('caregiver_name')->nullable(); // participants under 18
            $table->string('caregiver_contact')->nullable();
            $table->string('education_level', 20)->nullable();
            $table->string('current_status', 20)->nullable();
            $table->boolean('household_head')->nullable();
            $table->unsignedSmallInteger('dependents')->nullable();

            // Section 1: Vulnerability & target group
            $table->json('vulnerability')->nullable();

            // Section 2: Skills & livelihoods
            $table->string('current_income_source')->nullable();
            $table->decimal('average_monthly_income', 12, 2)->nullable();
            $table->json('skill_interests')->nullable();
            $table->string('entrepreneurship_experience', 20)->nullable();
            $table->string('business_idea')->nullable();

            // Section 3: Financial inclusion & support needs
            $table->json('financial_services_access')->nullable();
            $table->string('financial_literacy', 10)->nullable();
            $table->boolean('needs_startup_capital')->nullable();
            $table->boolean('interested_in_savings')->nullable();

            // Section 4: Empowerment & personal development
            $table->string('decision_making_confidence', 10)->nullable();
            $table->string('leadership_experience', 20)->nullable();
            $table->boolean('has_mentorship_access')->nullable();
            $table->boolean('interested_in_stem')->nullable();
            $table->boolean('needs_safe_spaces')->nullable();

            // Section 5: Market linkages & growth
            $table->boolean('has_market_access')->nullable();
            $table->boolean('member_of_youth_group')->nullable();
            $table->boolean('interested_in_incubation')->nullable();
            $table->string('scaling_potential', 10)->nullable();

            // Section 6: Consent & communication
            $table->boolean('consent_to_participate')->nullable();
            $table->string('preferred_communication', 10)->nullable();
            $table->string('signature_path')->nullable();
            $table->date('signed_date')->nullable();

            $table->timestamp('captured_at')->nullable(); // when captured on the device (may be offline, so != created_at)
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_profiles');
    }
};
