<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeneficiaryProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_uuid', 'consent_given',
        'full_name', 'age', 'gender', 'location', 'phone_number', 'alternative_contact',
        'caregiver_name', 'caregiver_contact', 'education_level', 'current_status',
        'household_head', 'dependents',
        'vulnerability',
        'current_income_source', 'average_monthly_income', 'skill_interests',
        'entrepreneurship_experience', 'business_idea',
        'financial_services_access', 'financial_literacy', 'needs_startup_capital', 'interested_in_savings',
        'decision_making_confidence', 'leadership_experience', 'has_mentorship_access',
        'interested_in_stem', 'needs_safe_spaces',
        'has_market_access', 'member_of_youth_group', 'interested_in_incubation', 'scaling_potential',
        'consent_to_participate', 'preferred_communication', 'signature_path', 'signed_date',
        'captured_at', 'created_by_user_id',
    ];

    protected $casts = [
        'consent_given'             => 'boolean',
        'household_head'            => 'boolean',
        'vulnerability'             => 'array',
        'average_monthly_income'    => 'decimal:2',
        'skill_interests'           => 'array',
        'financial_services_access' => 'array',
        'needs_startup_capital'     => 'boolean',
        'interested_in_savings'     => 'boolean',
        'has_mentorship_access'     => 'boolean',
        'interested_in_stem'        => 'boolean',
        'needs_safe_spaces'         => 'boolean',
        'has_market_access'         => 'boolean',
        'member_of_youth_group'     => 'boolean',
        'interested_in_incubation'  => 'boolean',
        'consent_to_participate'    => 'boolean',
        'signed_date'               => 'date',
        'captured_at'               => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
