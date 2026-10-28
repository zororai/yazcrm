<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSchemeTopic extends Model
{
    protected $fillable = [
        'school_class_id', 'school_subject_id', 'teacher_id', 'academic_year', 'term', 'week',
        'topic', 'objectives', 'planned_date', 'status', 'completed_date', 'notes',
    ];

    protected $casts = [
        'planned_date'   => 'date:Y-m-d',
        'completed_date' => 'date:Y-m-d',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(SchoolSubject::class, 'school_subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
