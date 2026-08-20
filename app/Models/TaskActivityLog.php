<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'task_id', 'user_id', 'action',
        'old_status', 'new_status', 'old_priority', 'new_priority',
        'changed_fields', 'reason',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'created_at'     => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
