<?php

namespace App\Notifications;

use App\Models\Leave\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestDecidedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly LeaveRequest $leave)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $leave = $this->leave->loadMissing('leaveType', 'approver');

        return [
            'type'             => 'leave_decided',
            'leave_request_id' => $leave->id,
            'status'           => $leave->status,
            'message'          => sprintf(
                'Your %s leave from %s was %s by %s.',
                $leave->leaveType->name, $leave->start_date->format('j M Y'), $leave->status, $leave->approver?->name ?? 'a manager',
            ),
        ];
    }
}
