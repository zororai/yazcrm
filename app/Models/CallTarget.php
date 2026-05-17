<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallTarget extends Model
{
    protected $fillable = ['agent_id', 'daily_target', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
