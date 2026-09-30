<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number', 'requisition_id', 'bid_process_id', 'supplier_id', 'store_id', 'department_id', 'requested_by', 'approved_by',
        'order_date', 'expected_delivery_date', 'currency', 'subtotal', 'tax', 'total', 'status', 'notes',
        'delivery_confirmed_by', 'delivered_at', 'delivery_notes', 'invoice_reference', 'invoice_date',
    ];

    protected $casts = [
        'order_date'              => 'date',
        'expected_delivery_date'  => 'date',
        'subtotal'                => 'decimal:2',
        'tax'                     => 'decimal:2',
        'total'                   => 'decimal:2',
        'delivered_at'            => 'datetime',
        'invoice_date'            => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequisition::class, 'requisition_id');
    }

    public function bidProcess(): BelongsTo
    {
        return $this->belongsTo(ProcurementBidProcess::class, 'bid_process_id');
    }

    public function deliveryConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_confirmed_by');
    }

    public function paymentRequisitions(): HasMany
    {
        return $this->hasMany(ProcurementPaymentRequisition::class, 'purchase_order_id');
    }
}
