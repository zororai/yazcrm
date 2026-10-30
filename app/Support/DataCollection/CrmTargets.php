<?php

namespace App\Support\DataCollection;

use App\Models\BeneficiaryActivity;
use App\Models\BeneficiaryProfile;
use App\Models\Client;
use App\Models\Ticket;

// The CRM records a form can be mapped onto, with the fields a designer may
// fill from form answers. Types drive the conversion of answers:
// string|text|int|decimal|bool|date|datetime|array.
class CrmTargets
{
    public static function all(): array
    {
        return [
            'beneficiary' => [
                'label'    => 'Beneficiary profile',
                'model'    => BeneficiaryProfile::class,
                'match'    => ['phone_number', 'full_name', 'alternative_contact'],
                'required' => ['full_name'],
                'fields'   => [
                    'full_name'               => ['Full name', 'string'],
                    'age'                     => ['Age', 'int'],
                    'gender'                  => ['Gender', 'string'],
                    'location'                => ['Location', 'string'],
                    'phone_number'            => ['Phone number', 'string'],
                    'alternative_contact'     => ['Alternative contact', 'string'],
                    'caregiver_name'          => ['Caregiver name', 'string'],
                    'caregiver_contact'       => ['Caregiver contact', 'string'],
                    'education_level'         => ['Education level', 'string'],
                    'current_status'          => ['Current status', 'string'],
                    'household_head'          => ['Household head', 'bool'],
                    'dependents'              => ['Dependents', 'int'],
                    'vulnerability'           => ['Vulnerability', 'array'],
                    'current_income_source'   => ['Current income source', 'string'],
                    'average_monthly_income'  => ['Average monthly income', 'decimal'],
                    'skill_interests'         => ['Skill interests', 'array'],
                    'business_idea'           => ['Business idea', 'string'],
                    'consent_to_participate'  => ['Consent to participate', 'bool'],
                    'preferred_communication' => ['Preferred communication', 'string'],
                ],
            ],
            'contact' => [
                'label'    => 'Contact (client)',
                'model'    => Client::class,
                'match'    => ['phone', 'email'],
                'required' => ['name', 'phone'],
                'fields'   => [
                    'name'            => ['Name', 'string'],
                    'phone'           => ['Phone', 'string'],
                    'email'           => ['Email', 'string'],
                    'company'         => ['Organisation', 'string'],
                    'whatsapp_number' => ['WhatsApp number', 'string'],
                    'notes'           => ['Notes', 'text'],
                ],
            ],
            'case' => [
                'label'    => 'Case (ticket)',
                'model'    => Ticket::class,
                'match'    => ['contact_number'],
                'required' => ['subject'],
                'fields'   => [
                    'subject'        => ['Subject', 'string'],
                    'description'    => ['Description', 'text'],
                    'contact_number' => ['Contact number', 'string'],
                    'priority'       => ['Priority (low/high/urgent)', 'string'],
                    'caller_age'     => ['Age', 'int'],
                    'caller_gender'  => ['Gender', 'string'],
                    'province'       => ['Province', 'string'],
                    'district'       => ['District', 'string'],
                    'location'       => ['Location', 'string'],
                    'project'        => ['Project', 'string'],
                    'follow_up_date' => ['Follow-up date', 'date'],
                ],
            ],
            'activity' => [
                'label'    => 'Beneficiary activity',
                'model'    => BeneficiaryActivity::class,
                'match'    => [],
                'required' => ['activity_name', 'activity_date'],
                'fields'   => [
                    'activity_name' => ['Activity name', 'string'],
                    'activity_date' => ['Activity date', 'date'],
                    'compiled_by'   => ['Compiled by', 'string'],
                    'reviewed_by'   => ['Reviewed by', 'string'],
                ],
            ],
        ];
    }

    public static function get(string $target): ?array
    {
        return self::all()[$target] ?? null;
    }
}
