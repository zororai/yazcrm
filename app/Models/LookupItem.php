<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LookupItem extends Model
{
    protected $fillable = ['type', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const TYPES = [
        'purpose_of_call'          => 'Purpose of Call',
        'service_requested'        => 'Services Requested',
        'second_service_requested' => 'Second Service Requested',
        'radio_channel'            => 'Radio Channel',
        'project'                  => 'Project',
        'key_pops'                 => 'Key Pops',
        'mode_of_communication'    => 'Mode of Communication',
        'referred_to'              => 'Referred To',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public static function forType(string $type)
    {
        return static::where('type', $type)->orderBy('sort_order')->orderBy('name')->get();
    }
}
