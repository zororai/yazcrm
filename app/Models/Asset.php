<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'asset_tag', 'name', 'type', 'location', 'ip_address', 'owner',
        'os_version', 'patch_status', 'data_sensitivity', 'criticality_393',
        'source', 'last_scanned_at', 'serial_number', 'supplier', 'acquired_on',
        'cost', 'warranty_expires_on', 'lifecycle_status', 'replace_due_on', 'notes',
    ];

    protected $casts = [
        'acquired_on'         => 'date',
        'warranty_expires_on' => 'date',
        'replace_due_on'      => 'date',
        'last_scanned_at'     => 'datetime',
        'cost'                => 'decimal:2',
    ];

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }

    public function scopeActive($query)
    {
        return $query->where('lifecycle_status', 'active');
    }

    public function scopeDueRefresh($query)
    {
        return $query->whereNotNull('replace_due_on')
                     ->where('replace_due_on', '<=', now()->addMonths(6));
    }

    public function scopeWarrantyExpiring($query)
    {
        return $query->whereNotNull('warranty_expires_on')
                     ->where('warranty_expires_on', '<=', now()->addMonths(3));
    }
}
