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

class ProjectOverdueNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public User $user,
        public int $overdueDays
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Project Overdue: {$this->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project-overdue',
            with: [
                'project' => $this->project,
                'user' => $this->user,
                'overdueDays' => $this->overdueDays,
                'projectUrl' => \App\Filament\Pages\ProjectBoard::getUrl(['project_id' => $this->project->id]),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

