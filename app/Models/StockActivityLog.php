<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'store_id', 'item_id', 'user_id', 'action',
        'reference_type', 'reference_id', 'quantity_change', 'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
