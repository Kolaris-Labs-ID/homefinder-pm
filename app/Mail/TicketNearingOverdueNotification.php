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

class TicketNearingOverdueNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public User $user,
        public int $daysUntilDue
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⏰ Ticket Due Soon: {$this->ticket->name} ({$this->ticket->uuid}) - {$this->daysUntilDue} day(s) remaining",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-nearing-overdue',
            with: [
                'ticket' => $this->ticket,
                'user' => $this->user,
                'daysUntilDue' => $this->daysUntilDue,
                'ticketUrl' => \App\Filament\Pages\ProjectBoard::getUrl(['project_id' => $this->ticket->project_id]),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

