<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UchatAnalyticsSnapshot extends Model
{
    protected $fillable = [
        'date', 'total_bot_users', 'new_bot_users', 'active_today', 'channel_counts',
    ];

    protected $casts = [
        'date'           => 'date',
        'channel_counts' => 'array',
    ];
}
