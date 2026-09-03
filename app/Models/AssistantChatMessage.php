<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantChatMessage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'role', 'content'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
