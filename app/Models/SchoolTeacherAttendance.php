<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolTeacherAttendance extends Model
{
    protected $fillable = ['teacher_id', 'date', 'status', 'time_in', 'time_out', 'note', 'recorded_by'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
