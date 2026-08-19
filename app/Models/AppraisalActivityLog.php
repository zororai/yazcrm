<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'appraisal_id', 'user_id', 'action', 'old_status', 'new_status', 'reason', 'changed_fields',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'created_at'     => 'datetime',
    ];

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
