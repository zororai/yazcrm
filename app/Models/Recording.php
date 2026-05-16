<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recording extends Model
{
    protected $fillable = [
        'call_id', 'file_name', 'file_path', 'file_url',
        'duration', 'file_size', 'format',
    ];

    public function call()
    {
        return $this->belongsTo(Call::class);
    }
}
