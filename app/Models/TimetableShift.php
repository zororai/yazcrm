<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableShift extends Model
{
    protected $fillable = ['user_id', 'work_date', 'shift_type', 'roster_label'];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
