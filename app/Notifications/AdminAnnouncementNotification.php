<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $postedBy, private readonly string $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'      => 'announcement',
            'posted_by' => $this->postedBy->name,
            'message'   => $this->message,
            'url'       => '/dashboard',
        ];
    }
}
