<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementRequisition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'requisition_number', 'requested_by', 'department_id', 'title', 'justification',
        'estimated_total', 'currency', 'required_by_date', 'status',
        'reviewed_by', 'reviewed_at', 'review_notes',
        'approved_by', 'approved_at', 'approval_notes',
    ];

    protected $casts = [
        'estimated_total'   => 'decimal:2',
        'required_by_date'  => 'date',
        'reviewed_at'       => 'datetime',
        'approved_at'       => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementRequisitionItem::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ProcurementRequisitionActivityLog::class, 'requisition_id')->latest('created_at');
    }

    public function bidProcess(): HasOne
    {
        return $this->hasOne(ProcurementBidProcess::class, 'requisition_id');
    }
}
