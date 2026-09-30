<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementBidProcess extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'requisition_id', 'initiated_by', 'bid_method', 'bid_reference', 'status',
        'recommended_supplier_id', 'evaluation_notes', 'evaluated_by', 'evaluated_at',
        'hop_reviewed_by', 'hop_reviewed_at', 'hop_notes',
        'hof_reviewed_by', 'hof_reviewed_at', 'hof_notes',
        'approved_by', 'approved_at', 'approval_notes',
    ];

    protected $casts = [
        'evaluated_at'     => 'datetime',
        'hop_reviewed_at'  => 'datetime',
        'hof_reviewed_at'  => 'datetime',
        'approved_at'      => 'datetime',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequisition::class, 'requisition_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function recommendedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'recommended_supplier_id');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function hopReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hop_reviewed_by');
    }

    public function hofReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hof_reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(ProcurementBidQuote::class, 'bid_process_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ProcurementBidActivityLog::class, 'bid_process_id')->latest('created_at');
    }
}
