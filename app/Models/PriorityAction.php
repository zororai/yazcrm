<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriorityAction extends Model
{
    protected $fillable = [
        'risk_id', 'action_ref', 'description', 'owner',
        'target_date', 'status', 'priority', 'completed_at',
    ];

    protected $casts = [
        'target_date'  => 'date',
        'completed_at' => 'datetime',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }
}
