<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallAiAnalysis extends Model
{
    protected $fillable = [
        'call_id', 'ai_summary', 'ai_category', 'ai_priority',
        'ai_follow_up_required', 'ai_referral_required', 'ai_model',
        'status', 'reviewed_summary', 'reviewed_by', 'reviewed_at',
        'analysis_status', 'error_message',
    ];

    protected $casts = [
        'ai_follow_up_required' => 'boolean',
        'ai_referral_required'  => 'boolean',
        'reviewed_at'           => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
