<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolStudent extends Model
{
    protected $fillable = [
        'admission_number', 'first_name', 'surname', 'gender', 'date_of_birth',
        'school_class_id', 'guardian_name', 'guardian_phone', 'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'is_active'     => 'boolean',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->surname}");
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SchoolStudentAttendance::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SchoolStudentResult::class);
    }
}
