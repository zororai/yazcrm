<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// An item refused by POST /submissions/batch. Never holds the submitted data.
class DataCollectionRejection extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['client_uuid', 'form_id', 'user_id', 'device_id', 'reason', 'errors'];

    protected $casts = [
        'errors'     => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
