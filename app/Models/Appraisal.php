<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appraisal extends Model
{
    protected $fillable = [
        'user_id', 'supervisor_id',
        'job_title', 'department', 'employee_number', 'start_date',
        'reviewer_name', 'date_of_review', 'next_review_date',
        'overall_rating', 'employee_responses', 'supervisor_responses',
        'status', 'submitted_at', 'completed_at', 'employee_signed_at', 'supervisor_signed_at',
    ];

    protected $casts = [
        'start_date'            => 'date',
        'date_of_review'        => 'date',
        'next_review_date'      => 'date',
        'employee_responses'    => 'array',
        'supervisor_responses'  => 'array',
        'submitted_at'          => 'datetime',
        'completed_at'          => 'datetime',
        'employee_signed_at'    => 'datetime',
        'supervisor_signed_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AppraisalActivityLog::class)->latest('created_at');
    }
}
