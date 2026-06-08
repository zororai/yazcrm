<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Control extends Model
{
    protected $fillable = [
        'risk_id', 'description', 'effectiveness',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }
}
