<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequisitionActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'procurement_req_activity_logs';

    protected $fillable = ['requisition_id', 'user_id', 'action', 'notes'];

    protected $casts = ['created_at' => 'datetime'];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequisition::class, 'requisition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
