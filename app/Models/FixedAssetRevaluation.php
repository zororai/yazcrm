<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetRevaluation extends Model
{
    protected $fillable = [
        'fixed_asset_id', 'revalued_by', 'revaluation_date', 'previous_value',
        'revalued_amount', 'new_useful_life_years', 'new_salvage_value', 'notes',
    ];

    protected $casts = [
        'revaluation_date'       => 'date',
        'previous_value'         => 'decimal:2',
        'revalued_amount'        => 'decimal:2',
        'new_salvage_value'      => 'decimal:2',
        'new_useful_life_years'  => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function revaluedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revalued_by');
    }
}
