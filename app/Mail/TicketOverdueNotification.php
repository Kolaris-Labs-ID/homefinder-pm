<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketOverdueNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public User $user,
        public int $overdueDays
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Ticket Overdue: {$this->ticket->name} ({$this->ticket->uuid})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-overdue',
            with: [
                'ticket' => $this->ticket,
                'user' => $this->user,
                'overdueDays' => $this->overdueDays,
                'ticketUrl' => \App\Filament\Pages\ProjectBoard::getUrl(['project_id' => $this->ticket->project_id]),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

