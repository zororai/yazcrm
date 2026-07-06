<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    protected $fillable = [
        'name', 'organisation', 'phone', 'email', 'physical_address',
        'services_offered', 'notes', 'chapter',
    ];
}
