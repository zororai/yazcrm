<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressReport extends Model
{
    protected $fillable = [
        'user_id', 'month', 'job_title', 'supervisor',
        'date_submitted', 'overall_progress', 'kpis', 'provinces',
        'male_clients', 'female_clients', 'services', 'activities', 'success_stories',
        'status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'month'          => 'date',
        'date_submitted' => 'date',
        'kpis'           => 'array',
        'provinces'      => 'array',
        'services'       => 'array',
        'activities'     => 'array',
        'success_stories' => 'array',
        'reviewed_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Success Stories (from the Success Stories module) attached to this
    // report. Separate from the free-text `success_stories` column above.
    public function attachedStories()
    {
        return $this->belongsToMany(SuccessStory::class, 'progress_report_success_story')->withTimestamps();
    }
}
