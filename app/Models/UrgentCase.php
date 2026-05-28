<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrgentCase extends Model
{
    protected $fillable = [
        'agent_id', 'subject', 'contact_number', 'description',
        'status', 'resolved_at', 'resolved_by_id',
        'source_ticket_id', 'created_ticket_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function sourceTicket()
    {
        return $this->belongsTo(Ticket::class, 'source_ticket_id');
    }

    public function createdTicket()
    {
        return $this->belongsTo(Ticket::class, 'created_ticket_id');
    }
}
