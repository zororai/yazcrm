<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementBidQuote extends Model
{
    protected $fillable = ['bid_process_id', 'supplier_id', 'quoted_amount', 'notes', 'is_recommended'];

    protected $casts = [
        'quoted_amount'  => 'decimal:2',
        'is_recommended' => 'boolean',
    ];

    public function bidProcess(): BelongsTo
    {
        return $this->belongsTo(ProcurementBidProcess::class, 'bid_process_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
