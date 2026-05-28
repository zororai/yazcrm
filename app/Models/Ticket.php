<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'call_id', 'client_id', 'agent_id', 'subject', 'contact_number', 'sisters_number', 'description',
        'status', 'priority', 'resolved_at', 'follow_up_date',
        'mode_of_communication', 'call_validity', 'purpose_of_call',
        'immediate_action_required', 'action_status', 'caller_age', 'caller_gender',
        'caller_marital_status', 'key_pops', 'province', 'district',
        'location', 'is_repeat_caller', 'project', 'services_requested_before', 'services_requested',
        'second_service_requested', 'number_of_services', 'referred_to',
        'uptake_confirmed', 'referral_uptake_date',
    ];

    protected $casts = [
        'resolved_at'              => 'datetime',
        'immediate_action_required' => 'boolean',
        'is_repeat_caller'         => 'boolean',
        'uptake_confirmed'         => 'boolean',
        'caller_age'               => 'integer',
        'number_of_services'       => 'integer',
    ];

    public function call()
    {
        return $this->belongsTo(Call::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
