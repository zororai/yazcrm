<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataCollectionFormMapping extends Model
{
    public const MODES = ['store_only', 'create', 'update'];

    protected $fillable = ['form_id', 'target', 'mode', 'match_crm_field', 'match_form_field', 'field_map', 'updated_by'];

    protected $casts = [
        'field_map' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DataCollectionForm::class, 'form_id');
    }
}
