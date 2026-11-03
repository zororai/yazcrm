<?php

namespace App\Models\Leave;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'leave_type_id', 'year', 'entitled_days', 'used_days'];

    protected $casts = [
        'year'          => 'integer',
        'entitled_days' => 'float',
        'used_days'     => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function getRemainingDaysAttribute(): float
    {
        return round($this->entitled_days - $this->used_days, 1);
    }
}
