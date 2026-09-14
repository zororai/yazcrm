<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableShiftSnapshot extends Model
{
    protected $fillable = [
        'start_date', 'end_date', 'agent_ids', 'old_shifts', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'agent_ids'  => 'array',
        'old_shifts' => 'array',
    ];
}
