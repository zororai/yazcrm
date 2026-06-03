<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SbcSignup extends Model
{
    protected $fillable = [
        'sheet', 'date', 'phone_number', 'first_name', 'surname',
        'age', 'sex', 'location', 'synced_at',
        'certificate_status', 'certificate_downloaded_at', 'whatsapp_sent_at',
        'cert_token', 'cert_download_count',
    ];

    protected $casts = [
        'date'                       => 'date',
        'synced_at'                  => 'datetime',
        'certificate_downloaded_at'  => 'datetime',
        'whatsapp_sent_at'           => 'datetime',
        'age'                        => 'integer',
    ];
}
