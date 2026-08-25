<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'item_id', 'description', 'quantity', 'unit_cost', 'line_total', 'quantity_received',
    ];

    protected $casts = [
        'unit_cost'  => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->quantity_received);
    }
}
