<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'fixed_asset_id', 'user_id', 'action', 'old_status', 'new_status', 'changed_fields', 'reason',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'created_at'     => 'datetime',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
