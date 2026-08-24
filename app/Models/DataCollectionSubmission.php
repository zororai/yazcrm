<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataCollectionSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'submission_uid', 'project_id', 'form_id', 'form_version_id', 'assignment_id',
        'submitted_by', 'status', 'answers', 'completion_percentage', 'started_at', 'submitted_at',
    ];

    protected $casts = [
        'answers'      => 'array',
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
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

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DataCollectionFormAssignment::class, 'assignment_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DataCollectionSubmissionReview::class, 'submission_id')->latest('created_at');
    }
}
