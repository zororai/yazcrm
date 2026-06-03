<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SbcSignup extends Model
{
    protected $fillable = [
        'sheet', 'date', 'phone_number', 'first_name', 'surname',
        'age', 'sex', 'location', 'synced_at',
        'certificate_status', 'certificate_downloaded_at',
    ];

    protected $casts = [
        'date'                       => 'date',
        'synced_at'                  => 'datetime',
        'certificate_downloaded_at'  => 'datetime',
        'age'                        => 'integer',
    ];
}
