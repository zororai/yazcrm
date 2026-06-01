<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'display_name', 'nav_permissions', 'is_system'];

    protected $casts = [
        'nav_permissions' => 'array',
        'is_system'       => 'boolean',
    ];
}
