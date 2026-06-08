<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedNumberRequest extends Model
{
    protected $fillable = [
        'requested_by', 'reviewed_by', 'name', 'numbers',
        'limit_type', 'status', 'rejection_reason', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
