<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LsjpCheckupPhoto extends Model
{
    protected $fillable = ['lsjp_checkup_id', 'path'];

    public function checkup(): BelongsTo
    {
        return $this->belongsTo(LsjpCheckup::class, 'lsjp_checkup_id');
    }
}
