<?php

namespace App\Services\Leave;

use App\Models\Leave\LeaveBalance;
use App\Models\Leave\LeaveRequest;
use App\Models\Leave\LeaveType;
use App\Models\User;
use App\Notifications\LeaveRequestDecidedNotification;
use App\Notifications\LeaveRequestSubmittedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Every leave state change goes through here, so the balance rules live in
// one place: days are only deducted on approval, inside a transaction, and
// given back if an approved request is later corrected away.
class LeaveService
{
    // The balance row for a user, type and year, created from the type's
    // default entitlement the first time it is needed.
    public function balanceFor(User $user, LeaveType $type, int $year, bool $lock = false): LeaveBalance
    {
        $query = LeaveBalance::where(['user_id' => $user->id, 'leave_type_id' => $type->id, 'year' => $year]);
        $balance = ($lock ? $query->lockForUpdate() : $query)->first();

        return $balance ?? LeaveBalance::create([
            'user_id'       => $user->id,
            'leave_type_id' => $type->id,
            'year'          => $year,
            'entitled_days' => $type->default_days,
            'used_days'     => 0,
        ]);
    }

    public function pendingDays(User $user, LeaveType $type, int $year): float
    {
        return (float) LeaveRequest::where('user_id', $user->id)
            ->where('leave_type_id', $type->id)
            ->where('status', LeaveRequest::PENDING)
            ->whereYear('start_date', $year)
            ->sum('days');
    }

    /** @param array{leave_type_id:int,start_date:string,end_date:string,reason?:?string} $data */
    public function submit(User $user, array $data, ?UploadedFile $attachment = null): LeaveRequest
    {
        $type  = LeaveType::where('is_active', true)->findOrFail($data['leave_type_id']);
        $start = CarbonImmutable::parse($data['start_date']);
        $end   = CarbonImmutable::parse($data['end_date']);

        if ($start->year !== $end->year) {
            throw ValidationException::withMessages([
                'end_date' => 'Leave cannot run across 31 December. Apply for each year separately.',
            ]);
        }

        $days = WorkingDays::count($start, $end);
        if ($days === 0) {
            throw ValidationException::withMessages([
                'start_date' => 'The dates you picked are all weekends or public holidays.',
            ]);
        }

        if ($type->requires_attachment && ! $attachment) {
            throw ValidationException::withMessages([
                'attachment' => "{$type->name} leave needs a supporting document.",
            ]);
        }

        $leave = DB::transaction(function () use ($user, $type, $start, $end, $days, $data, $attachment) {
            // Lock the user's row so two submissions can't both pass the
            // overlap and balance checks.
            User::whereKey($user->id)->lockForUpdate()->first();

            $overlaps = LeaveRequest::where('user_id', $user->id)
                ->active()
                ->overlapping($start->toDateString(), $end->toDateString())
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'start_date' => 'These dates overlap a leave request you already have pending or approved.',
                ]);
            }

            if ($type->is_paid) {
                $balance   = $this->balanceFor($user, $type, $start->year);
                $pending   = $this->pendingDays($user, $type, $start->year);
                $available = max(0, $balance->entitled_days - $balance->used_days - $pending);

                if ($days > $available) {
                    throw ValidationException::withMessages([
                        'end_date' => sprintf(
                            'This is %d working day(s), but you only have %s day(s) of %s leave left%s.',
                            $days, self::fmt($available), $type->name,
                            $pending > 0 ? ' after your pending requests' : '',
                        ),
                    ]);
                }
            }

            return LeaveRequest::create([
                'user_id'         => $user->id,
                'leave_type_id'   => $type->id,
                'start_date'      => $start->toDateString(),
                'end_date'        => $end->toDateString(),
                'days'            => $days,
                'reason'          => $data['reason'] ?? null,
                'attachment_path' => $attachment?->store("leave-attachments/{$user->id}", 'local'),
                'status'          => LeaveRequest::PENDING,
            ]);
        });

        $manager = $user->supervisor;
        if ($manager && $manager->is_active) {
            $manager->notify(new LeaveRequestSubmittedNotification($leave));
        }

        return $leave;
    }

    public function approve(User $approver, LeaveRequest $leave, ?string $comment = null): LeaveRequest
    {
        $leave = DB::transaction(function () use ($approver, $leave, $comment) {
            $leave = LeaveRequest::with('leaveType', 'user')->lockForUpdate()->findOrFail($leave->id);
            $this->ensurePending($leave);
            $this->deduct($leave);

            $leave->update([
                'status'           => LeaveRequest::APPROVED,
                'approver_id'      => $approver->id,
                'approver_comment' => $comment,
                'decided_at'       => now(),
            ]);

            return $leave;
        });

        $leave->user->notify(new LeaveRequestDecidedNotification($leave));

        return $leave;
    }

    public function reject(User $approver, LeaveRequest $leave, string $comment): LeaveRequest
    {
        $leave = DB::transaction(function () use ($approver, $leave, $comment) {
            $leave = LeaveRequest::with('leaveType', 'user')->lockForUpdate()->findOrFail($leave->id);
            $this->ensurePending($leave);

            $leave->update([
                'status'           => LeaveRequest::REJECTED,
                'approver_id'      => $approver->id,
                'approver_comment' => $comment,
                'decided_at'       => now(),
            ]);

            return $leave;
        });

        $leave->user->notify(new LeaveRequestDecidedNotification($leave));

        return $leave;
    }

    public function cancel(LeaveRequest $leave): LeaveRequest
    {
        return DB::transaction(function () use ($leave) {
            $leave = LeaveRequest::lockForUpdate()->findOrFail($leave->id);
            $this->ensurePending($leave);

            $leave->update(['status' => LeaveRequest::CANCELLED, 'cancelled_at' => now()]);

            return $leave;
        });
    }

    // A leave admin reverses or corrects an approved or rejected request.
    // Returns the before/after line for the audit trail.
    public function correct(User $admin, LeaveRequest $leave, string $status, string $reason, ?string $comment = null): string
    {
        return DB::transaction(function () use ($admin, $leave, $status, $reason, $comment) {
            $leave = LeaveRequest::with('leaveType', 'user')->lockForUpdate()->findOrFail($leave->id);
            $old = $leave->status;
            $oldComment = $leave->approver_comment;

            if ($old === $status) {
                throw ValidationException::withMessages(['status' => "This request is already {$status}."]);
            }

            if ($old === LeaveRequest::APPROVED) {
                $this->restore($leave);
            } elseif ($status === LeaveRequest::APPROVED) {
                $this->deduct($leave);
            }

            $leave->update([
                'status'            => $status,
                'approver_comment'  => $comment ?? $leave->approver_comment,
                'cancelled_at'      => $status === LeaveRequest::CANCELLED ? now() : $leave->cancelled_at,
                'corrected_by'      => $admin->id,
                'corrected_at'      => now(),
                'correction_reason' => $reason,
            ]);

            $description = sprintf(
                "Corrected leave request #%d (%s, %s leave %s to %s, %s days): status '%s' → '%s'",
                $leave->id, $leave->user->name, $leave->leaveType->name,
                $leave->start_date->toDateString(), $leave->end_date->toDateString(), self::fmt($leave->days),
                $old, $status,
            );
            if ($comment !== null && $comment !== $oldComment) {
                $description .= sprintf("; comment '%s' → '%s'", $oldComment ?? '', $comment);
            }

            return $description.'. Reason: '.$reason;
        });
    }

    private function ensurePending(LeaveRequest $leave): void
    {
        if (! $leave->isPending()) {
            throw ValidationException::withMessages(['status' => "This request is already {$leave->status}."]);
        }
    }

    // Caller holds a transaction; the request row is already locked.
    private function deduct(LeaveRequest $leave): void
    {
        $type    = $leave->leaveType;
        $year    = $leave->start_date->year;
        $balance = $this->balanceFor($leave->user, $type, $year, lock: true);

        if ($type->is_paid && $leave->days > $balance->entitled_days - $balance->used_days) {
            throw ValidationException::withMessages([
                'days' => sprintf(
                    '%s only has %s day(s) of %s leave left for %d.',
                    $leave->user->name, self::fmt($balance->remaining_days), $type->name, $year,
                ),
            ]);
        }

        $balance->update(['used_days' => $balance->used_days + $leave->days]);
    }

    private function restore(LeaveRequest $leave): void
    {
        $balance = $this->balanceFor($leave->user, $leave->leaveType, $leave->start_date->year, lock: true);
        $balance->update(['used_days' => max(0, $balance->used_days - $leave->days)]);
    }

    public static function fmt(float $days): string
    {
        return rtrim(rtrim(number_format($days, 1, '.', ''), '0'), '.');
    }
}
