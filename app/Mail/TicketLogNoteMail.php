<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketNote;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// A ticket's log note, emailed from the Log Notes box on the ticket page.
// Deliberately leaves out the caller's phone numbers — recipients follow the
// link (and log in) for the full case.
class TicketLogNoteMail extends Mailable
{
    public function __construct(public Ticket $ticket, public TicketNote $note)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ticket #{$this->ticket->id} log note: {$this->ticket->subject}",
            replyTo: $this->note->user?->email ? [$this->note->user->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ticket-log-note', with: ['url' => url("/tickets/{$this->ticket->id}")]);
    }
}
