<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetInspection extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'fixed_asset_id', 'inspector_id', 'inspected_at', 'condition',
        'working_status', 'damage_notes', 'comments',
    ];

    protected $casts = [
        'inspected_at' => 'date',
        'created_at'   => 'datetime',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
