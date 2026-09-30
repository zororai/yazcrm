<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPaymentActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['payment_id', 'user_id', 'action', 'notes'];

    protected $casts = ['created_at' => 'datetime'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ProcurementPaymentRequisition::class, 'payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
