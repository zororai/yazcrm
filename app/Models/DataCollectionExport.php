<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataCollectionExport extends Model
{
    protected $fillable = ['form_id', 'user_id', 'format', 'options', 'status', 'disk', 'path', 'row_count', 'error'];

    protected $casts = [
        'options' => 'array',
    ];

    protected $hidden = ['disk', 'path'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DataCollectionForm::class, 'form_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
