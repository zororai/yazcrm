<?php

namespace App\Models\Leave;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'default_days', 'requires_attachment', 'is_paid', 'is_active'];

    protected $casts = [
        'default_days'        => 'float',
        'requires_attachment' => 'boolean',
        'is_paid'             => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
