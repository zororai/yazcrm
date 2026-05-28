<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallTarget extends Model
{
    protected $fillable = ['agent_id', 'daily_target', 'start_date', 'end_date', 'target_day'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'target_day' => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
