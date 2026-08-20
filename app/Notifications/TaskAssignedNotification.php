<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Task $task, private readonly User $assignedBy)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'task_assigned',
            'task_id'      => $this->task->id,
            'assigned_by'  => $this->assignedBy->name,
            'message'      => "{$this->assignedBy->name} assigned you to \"{$this->task->title}\".",
            'url'          => "/tasks/{$this->task->id}",
        ];
    }
}
