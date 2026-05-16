<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallbackQueue extends Model
{
    protected $table = 'callback_queue';

    protected $fillable = [
        'client_id', 'call_id', 'agent_id', 'phone',
        'status', 'priority', 'scheduled_at', 'completed_at', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function call()
    {
        return $this->belongsTo(Call::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
