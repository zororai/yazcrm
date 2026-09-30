<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequisitionItem extends Model
{
    protected $fillable = [
        'procurement_requisition_id', 'item_id', 'description', 'quantity', 'estimated_unit_cost', 'line_total',
    ];

    protected $casts = [
        'estimated_unit_cost' => 'decimal:2',
        'line_total'          => 'decimal:2',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequisition::class, 'procurement_requisition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
