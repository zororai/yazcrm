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
        'uptake_confirmed', 'referral_uptake_date', 'classification', 'psychosocial_type',
        'services_requested_before_other', 'services_requested_other', 'second_service_requested_other',
    ];

    // Service dropdowns whose "Other (Specify):" choice comes with a typed detail
    // stored in "<field>_other".
    public const SPECIFY_FIELDS = ['services_requested_before', 'services_requested', 'second_service_requested'];

    public static function isOtherService(?string $value): bool
    {
        return $value !== null && str_starts_with(strtolower(trim($value)), 'other');
    }

    public static function isHighPriority(?string $priority): bool
    {
        return in_array($priority, ['high', 'urgent'], true);
    }

    // Follow-up plan by priority, in days from when the ticket is logged:
    // High / Urgent are followed up within 48 hours and the referral taken up
    // within 96; Low is followed up within 7 days (no uptake deadline).
    public const FOLLOW_UP_DAYS = ['high' => 2, 'urgent' => 2, 'low' => 7];
    public const UPTAKE_DAYS    = ['high' => 4, 'urgent' => 4];

    /** @return array{follow_up_date: ?string, referral_uptake_date: ?string} */
    public static function followUpPlan(?string $priority, ?\DateTimeInterface $from = null): array
    {
        $from = \Carbon\CarbonImmutable::instance($from ?? now());
        $in   = fn (?int $days) => $days === null ? null : $from->addDays($days)->toDateString();

        return [
            'follow_up_date'       => $in(self::FOLLOW_UP_DAYS[$priority] ?? null),
            'referral_uptake_date' => $in(self::UPTAKE_DAYS[$priority] ?? null),
        ];
    }

    // Fills the plan's dates into $data where the agent left them empty.
    public static function withFollowUpPlan(array $data, ?string $priority): array
    {
        foreach (self::followUpPlan($priority) as $field => $date) {
            if ($date !== null && empty($data[$field])) {
                $data[$field] = $date;
            }
        }

        return $data;
    }

    // Open cases whose follow-up or expected referral uptake falls due today
    // or earlier — what the agent's "follow-ups due" pop-up lists.
    public function scopeFollowUpDue($query)
    {
        $today = now()->toDateString();

        return $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', $today)
                ->whereNotIn('status', ['resolved', 'closed']))
            ->orWhere(fn ($q) => $q->whereNotNull('referral_uptake_date')->whereDate('referral_uptake_date', '<=', $today)
                ->where(fn ($q) => $q->where('uptake_confirmed', false)->orWhereNull('uptake_confirmed'))
                ->where('status', '!=', 'closed')));
    }

    // Puts the ticket on the Urgent Cases board (once): used for "Immediate
    // action required" and for High / Urgent priority tickets.
    public function openUrgentCase(?int $agentId = null): UrgentCase
    {
        $existing = UrgentCase::where('source_ticket_id', $this->id)->orWhere('created_ticket_id', $this->id)->first();
        if ($existing) return $existing;

        $resolved = in_array($this->status, ['resolved', 'closed'], true);

        return UrgentCase::create([
            'agent_id'         => $agentId ?? $this->agent_id,
            'subject'          => $this->subject,
            'contact_number'   => $this->contact_number,
            'description'      => $this->description,
            'status'           => $resolved ? 'resolved' : 'open',
            'resolved_at'      => $resolved ? ($this->resolved_at ?? $this->updated_at) : null,
            'source_ticket_id' => $this->id,
        ]);
    }

    // The service as it should read in exports, e.g. "Other (Specify): Legal aid".
    public function serviceWithDetail(string $field): string
    {
        $value  = (string) ($this->{$field} ?? '');
        $detail = $this->{"{$field}_other"} ?? null;

        return self::isOtherService($value) && $detail ? rtrim($value, ': ') . ': ' . $detail : $value;
    }

    protected $casts = [
        'classification'            => 'array',
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

    public function successStory()
    {
        return $this->hasOne(SuccessStory::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function notes()
    {
        return $this->hasMany(TicketNote::class)->latest();
    }
}
