<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Sent to a ticket's agent on the ticket's follow-up date (see the
// tickets:follow-up-reminders command). Shows in the bell; also emailed once
// the app has a real mail server configured.
class FollowUpDueNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Ticket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return config('mail.default') !== 'log' && filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        // Key order matters: the command looks for an existing reminder by
        // matching '"ticket_id":N,"follow_up_date":"Y-m-d"' in the stored JSON.
        return [
            'type'           => 'follow_up_due',
            'ticket_id'      => $this->ticket->id,
            'follow_up_date' => $this->dueDate(),
            'message'        => "Follow-up due today: ticket #{$this->ticket->id} \"{$this->ticket->subject}\""
                .($this->ticket->priority ? ' ('.ucfirst($this->ticket->priority).')' : '').'.',
            'url'            => "/tickets/{$this->ticket->id}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Follow-up due today: ticket #{$this->ticket->id}")
            ->line("Ticket #{$this->ticket->id} \"{$this->ticket->subject}\" is due for follow-up today.")
            ->action('Open the ticket', url("/tickets/{$this->ticket->id}"))
            ->line('Log what you did in the ticket\'s Log Notes.');
    }

    public function dueDate(): string
    {
        return substr((string) $this->ticket->follow_up_date, 0, 10);
    }
}
