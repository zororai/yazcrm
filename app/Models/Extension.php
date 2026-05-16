<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    protected $fillable = [
        'extension_number', 'name', 'status', 'user_id',
        'caller_id_name', 'email', 'voicemail_enabled',
    ];

    protected $casts = [
        'voicemail_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function calls()
    {
        return $this->hasMany(Call::class, 'extension_number', 'extension_number');
    }
}
