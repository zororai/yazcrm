<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'call_id', 'caller', 'callee', 'direction', 'status',
        'duration', 'started_at', 'ended_at', 'recording_file',
        'extension_number', 'client_id', 'agent_id', 'raw_data',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function recording()
    {
        return $this->hasOne(Recording::class);
    }

    public function ticket()
    {
        return $this->hasOne(Ticket::class);
    }

    public function callbackQueue()
    {
        return $this->hasOne(CallbackQueue::class);
    }

    public function getDurationFormattedAttribute(): string
    {
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
