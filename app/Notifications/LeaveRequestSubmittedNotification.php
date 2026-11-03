<?php

namespace App\Notifications;

use App\Models\Leave\LeaveRequest;
use App\Services\Leave\LeaveService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmittedNotification extends Notification
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
        $leave = $this->leave->loadMissing('user', 'leaveType');

        return [
            'type'             => 'leave_submitted',
            'leave_request_id' => $leave->id,
            'message'          => sprintf(
                '%s applied for %s day(s) of %s leave from %s.',
                $leave->user->name, LeaveService::fmt($leave->days), $leave->leaveType->name, $leave->start_date->format('j M Y'),
            ),
            'url'              => '/leave/requests?status=pending',
        ];
    }
}
