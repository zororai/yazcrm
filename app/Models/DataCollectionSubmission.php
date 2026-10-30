<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// One filled-in form from the app. submitted_by is always the authenticated
// user who sent it, never a value from the payload. Deleting is soft: this
// is data about young people and the trail must stay complete.
class DataCollectionSubmission extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'not_approved', 'on_hold'];

    protected $fillable = [
        'client_uuid', 'form_id', 'form_version_id', 'submitted_by', 'data',
        'started_at', 'collected_at', 'received_at', 'device_id', 'app_version', 'lat', 'lng',
        'completion', 'status', 'status_note', 'status_by', 'status_at',
        'crm_record_type', 'crm_record_id', 'processed_at',
    ];

    protected $casts = [
        'data'         => 'array',
        'started_at'   => 'datetime',
        'collected_at' => 'datetime',
        'received_at'  => 'datetime',
        'status_at'    => 'datetime',
        'processed_at' => 'datetime',
        'lat'          => 'float',
        'lng'          => 'float',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DataCollectionForm::class, 'form_id');
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(DataCollectionFormVersion::class, 'form_version_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function statusBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(DataCollectionSubmissionMedia::class, 'submission_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(DataCollectionSubmissionAudit::class, 'submission_id')->latest('id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(DataCollectionSubmissionValue::class, 'submission_id');
    }
}
