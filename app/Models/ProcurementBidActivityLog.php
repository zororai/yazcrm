<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementBidActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['bid_process_id', 'user_id', 'action', 'notes'];

    protected $casts = ['created_at' => 'datetime'];

    public function bidProcess(): BelongsTo
    {
        return $this->belongsTo(ProcurementBidProcess::class, 'bid_process_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
