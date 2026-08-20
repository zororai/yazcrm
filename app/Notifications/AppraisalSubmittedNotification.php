<?php

namespace App\Notifications;

use App\Models\Appraisal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppraisalSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Appraisal $appraisal)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'appraisal_submitted',
            'appraisal_id' => $this->appraisal->id,
            'employee'     => $this->appraisal->user?->name,
            'message'      => "{$this->appraisal->user?->name} submitted their appraisal for your review.",
            'url'          => "/appraisals/{$this->appraisal->id}/review",
        ];
    }
}
