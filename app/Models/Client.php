<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'company', 'notes',
        'whatsapp_number', 'avatar', 'status',
    ];

    public function calls()
    {
        return $this->hasMany(Call::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function callbackQueue()
    {
        return $this->hasMany(CallbackQueue::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
