<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReport extends Model
{
    protected $fillable = [
        'compiled_by', 'reviewer_id', 'approver_id', 'viewer_ids',
        'name_of_activity', 'date', 'district', 'organized_by', 'officer_in_charge', 'venue',
        'attendance', 'objectives', 'methodology', 'narration', 'key_outcomes', 'challenges',
        'action_items', 'pictures_link', 'impact_quotes',
        'status', 'compiled_at', 'reviewed_at', 'approved_at',
    ];

    protected $casts = [
        'date'          => 'date',
        'viewer_ids'    => 'array',
        'attendance'    => 'array',
        'action_items'  => 'array',
        'impact_quotes' => 'array',
        'compiled_at'   => 'datetime',
        'reviewed_at'   => 'datetime',
        'approved_at'   => 'datetime',
    ];

    public function compiler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'compiled_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
