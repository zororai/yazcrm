<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressReport extends Model
{
    protected $fillable = [
        'user_id', 'month', 'job_title', 'supervisor',
        'date_submitted', 'overall_progress', 'activities',
    ];

    protected $casts = [
        'month'          => 'date',
        'date_submitted' => 'date',
        'activities'     => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
