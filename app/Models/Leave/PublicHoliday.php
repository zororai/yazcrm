<?php

namespace App\Models\Leave;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'date'];

    protected $casts = ['date' => 'date'];

    // Stored as a bare Y-m-d on every driver, so the unique index and plain
    // date comparisons behave the same on SQLite (tests) as on MySQL.
    protected function date(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->toDateString());
    }
}
