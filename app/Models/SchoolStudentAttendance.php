<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolStudentAttendance extends Model
{
    protected $fillable = ['school_student_id', 'school_class_id', 'date', 'status', 'note', 'recorded_by'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(SchoolStudent::class, 'school_student_id');
    }
}
