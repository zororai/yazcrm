<?php

namespace App\Models\Leave;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    public const PENDING   = 'pending';
    public const APPROVED  = 'approved';
    public const REJECTED  = 'rejected';
    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::PENDING, self::APPROVED, self::REJECTED, self::CANCELLED];

    protected $fillable = [
        'user_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'reason', 'attachment_path',
        'status', 'approver_id', 'approver_comment', 'decided_at', 'cancelled_at',
        'corrected_by', 'corrected_at', 'correction_reason',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'days'         => 'float',
        'decided_at'   => 'datetime',
        'cancelled_at' => 'datetime',
        'corrected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    // Pending and approved requests hold their dates; rejected and cancelled
    // ones free them up again.
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::PENDING, self::APPROVED]);
    }

    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start);
    }
}
