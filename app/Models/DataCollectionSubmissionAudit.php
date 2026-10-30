<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Append-only: edits, status changes, deletes and CRM mapping per submission.
class DataCollectionSubmissionAudit extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['submission_id', 'user_id', 'action', 'old', 'new', 'note'];

    protected $casts = [
        'old'        => 'array',
        'new'        => 'array',
        'created_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DataCollectionSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
