<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolStudentResult extends Model
{
    protected $fillable = [
        'school_student_id', 'school_subject_id', 'school_class_id', 'academic_year',
        'term', 'assessment', 'mark', 'comment', 'recorded_by',
    ];

    protected $casts = [
        'mark' => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(SchoolStudent::class, 'school_student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(SchoolSubject::class, 'school_subject_id');
    }
}
