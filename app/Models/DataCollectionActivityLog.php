<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataCollectionActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'project_id', 'form_id', 'form_version_id', 'submission_id', 'user_id', 'action', 'changed_fields', 'reason',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'created_at'     => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DataCollectionProject::class, 'project_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(DataCollectionForm::class, 'form_id');
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(DataCollectionFormVersion::class, 'form_version_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DataCollectionSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
