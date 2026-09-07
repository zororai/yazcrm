<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuccessStory extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'recording_id', 'title', 'story',
        'status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recording()
    {
        return $this->belongsTo(Recording::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function photos()
    {
        return $this->hasMany(SuccessStoryPhoto::class);
    }
}
