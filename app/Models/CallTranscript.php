<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallTranscript extends Model
{
    protected $fillable = [
        'call_id', 'language', 'model', 'transcript', 'confidence',
        'processing_time_ms', 'status', 'error_message',
        'requested_at', 'completed_at',
    ];

    protected $casts = [
        'confidence'    => 'float',
        'requested_at'  => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
