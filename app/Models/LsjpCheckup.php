<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LsjpCheckup extends Model
{
    // How the person is doing at the check-up.
    public const PROGRESS = [
        'thriving'    => 'Doing well / growing',
        'progressing' => 'Making progress',
        'struggling'  => 'Struggling',
        'not_started' => 'Not yet started using the skill',
        'stopped'     => 'Stopped / dropped out',
    ];

    protected $fillable = [
        'lsjp_participant_id', 'month', 'conducted_on', 'progress', 'activity_status',
        'challenges', 'comment', 'referred_to', 'referral_notes', 'conducted_by',
    ];

    protected $casts = [
        'conducted_on' => 'date',
        'month'        => 'integer',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(LsjpParticipant::class, 'lsjp_participant_id');
    }

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(LsjpCheckupPhoto::class);
    }
}
