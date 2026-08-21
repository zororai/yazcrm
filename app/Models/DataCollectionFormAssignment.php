<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataCollectionFormAssignment extends Model
{
    protected $fillable = [
        'form_id', 'form_version_id', 'assigned_to', 'assigned_by',
        'start_date', 'due_date', 'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date'   => 'date',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DataCollectionForm::class, 'form_id');
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(DataCollectionFormVersion::class, 'form_version_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DataCollectionSubmission::class, 'assignment_id');
    }
}
