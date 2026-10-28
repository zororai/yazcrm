<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $fillable = ['name', 'grade_level', 'academic_year', 'class_teacher_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(SchoolStudent::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SchoolTeachingAssignment::class);
    }
}
