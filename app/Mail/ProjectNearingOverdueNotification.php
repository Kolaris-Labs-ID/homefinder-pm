<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectNearingOverdueNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public User $user,
        public int $daysUntilDue
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⏰ Project Due Soon: {$this->project->name} - {$this->daysUntilDue} day(s) remaining",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project-nearing-overdue',
            with: [
                'project' => $this->project,
                'user' => $this->user,
                'daysUntilDue' => $this->daysUntilDue,
                'projectUrl' => \App\Filament\Pages\ProjectBoard::getUrl(['project_id' => $this->project->id]),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

