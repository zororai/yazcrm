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
        'name', 'first_name', 'surname', 'username', 'email', 'phone', 'bio', 'password', 'role', 'supervisor_id',
        'avatar', 'is_active', 'last_login_at', 'nav_permissions', 'must_change_password', 'profile_prompt_dismiss_count',
    ];

    protected $appends = ['profile_complete'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'last_login_at'        => 'datetime',
        'is_active'             => 'boolean',
        'nav_permissions'       => 'array',
        'must_change_password' => 'boolean',
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

    public function callTarget()
    {
        return $this->hasOne(CallTarget::class, 'agent_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getProfileCompleteAttribute(): bool
    {
        return filled($this->phone) && filled($this->bio) && filled($this->avatar)
            && filled($this->first_name) && filled($this->surname) && filled($this->username);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    public function appraisals()
    {
        return $this->hasMany(Appraisal::class);
    }
}
