<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementPaymentRequisition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_number', 'purchase_order_id', 'prepared_by', 'payee_name', 'amount', 'currency',
        'payment_method', 'description', 'status',
        'reviewed_by', 'reviewed_at', 'review_notes',
        'approved_by', 'approved_at', 'approval_notes',
        'loaded_by', 'loaded_at', 'bank_reference', 'loading_notes',
        'released_by', 'released_at', 'release_notes',
        'recorded_by', 'recorded_at', 'recording_reference', 'recording_notes',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'reviewed_at'  => 'datetime',
        'approved_at'  => 'datetime',
        'loaded_at'    => 'datetime',
        'released_at'  => 'datetime',
        'recorded_at'  => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function loadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ProcurementPaymentActivityLog::class, 'payment_id')->latest('created_at');
    }
}
