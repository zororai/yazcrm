<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'avatar', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function extension()
    {
        return $this->hasOne(Extension::class);
    }

    public function calls()
    {
        return $this->hasMany(Call::class, 'agent_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'agent_id');
    }

    public function callbackQueue()
    {
        return $this->hasMany(CallbackQueue::class, 'agent_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
