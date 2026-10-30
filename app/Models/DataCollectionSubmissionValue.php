<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Flattened answer row (see the reshape migration). Rebuilt, never edited.
class DataCollectionSubmissionValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['submission_id', 'form_id', 'field', 'instance', 'value', 'value_num', 'value_date'];
}
