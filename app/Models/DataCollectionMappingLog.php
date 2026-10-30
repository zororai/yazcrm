<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataCollectionMappingLog extends Model
{
    protected $fillable = ['submission_id', 'form_id', 'result', 'crm_record_type', 'crm_record_id', 'error', 'attempts'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DataCollectionSubmission::class, 'submission_id')->withTrashed();
    }
}
