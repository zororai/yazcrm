<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'item_code', 'name', 'category_id', 'description', 'unit_of_measure',
        'minimum_stock', 'maximum_stock', 'reorder_level', 'default_store_id',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function storeStock(): HasMany
    {
        return $this->hasMany(StoreStock::class);
    }

    public function totalQuantity(): int
    {
        return $this->storeStock()->sum('quantity');
    }
}
