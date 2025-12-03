<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Services\ProjectArchiveService;

/**
 * Observer for Ticket model to handle auto-done detection.
 *
 * This observer monitors ticket changes and triggers project done status
 * checks when tickets are saved or deleted.
 *
 * Requirements: 3.1, 3.2, 3.3
 */
class TicketObserver
{
    protected ProjectArchiveService $archiveService;

    public function __construct(ProjectArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    /**
     * Handle the Ticket "saved" event.
     *
     * Triggered when a ticket is created or updated.
     * Checks if the project's done status should be updated based on ticket completion.
     *
     * Requirements: 3.1, 3.2, 3.3
     */
    public function saved(Ticket $ticket): void
    {
        if ($ticket->project) {
            $this->archiveService->checkAndUpdateDoneStatus($ticket->project);
        }
    }

    /**
     * Handle the Ticket "deleted" event.
     *
     * Triggered when a ticket is deleted.
     * Checks if the project's done status should be updated after ticket removal.
     *
     * Requirements: 3.1, 3.2, 3.3
     */
    public function deleted(Ticket $ticket): void
    {
        if ($ticket->project) {
            $this->archiveService->checkAndUpdateDoneStatus($ticket->project);
        }
    }
}
