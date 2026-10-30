<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataCollectionSubmissionMedia extends Model
{
    protected $table = 'data_collection_submission_media';

    protected $fillable = ['submission_id', 'field_name', 'disk', 'path', 'original_name', 'mime', 'size', 'checksum'];

    protected $hidden = ['disk', 'path'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(DataCollectionSubmission::class, 'submission_id');
    }
}
