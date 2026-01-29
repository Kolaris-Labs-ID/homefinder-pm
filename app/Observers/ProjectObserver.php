<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\ProjectArchiveService;

/**
 * Observer for Project model to handle automatic ticket archiving.
 *
 * This observer monitors project status changes and triggers ticket archiving
 * when a project is marked as done or archived.
 */
class ProjectObserver
{
    protected ProjectArchiveService $archiveService;

    /**
     * Static storage for previous state to avoid polluting model attributes.
     */
    protected static array $previousState = [];

    public function __construct(ProjectArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    /**
     * Handle the Project "updating" event.
     *
     * Captures the previous state before the update is applied.
     */
    public function updating(Project $project): void
    {
        // Store original values in static array to avoid adding to model attributes
        static::$previousState[$project->id] = [
            'wasArchived' => $project->getOriginal('archived_at') !== null,
            'wasDone' => $project->getOriginal('done_at') !== null,
        ];
    }

    /**
     * Handle the Project "updated" event.
     *
     * Triggered when a project is updated.
     * Checks if archived_at or done_at changed and archives/restores tickets accordingly.
     */
    public function updated(Project $project): void
    {
        // Check if archived_at or done_at changed
        if ($project->wasChanged(['archived_at', 'done_at'])) {
            $previousState = static::$previousState[$project->id] ?? ['wasArchived' => false, 'wasDone' => false];
            $wasArchived = $previousState['wasArchived'];
            $wasDone = $previousState['wasDone'];

            $this->archiveService->handleProjectStatusChange($project, $wasArchived, $wasDone);

            // Clean up stored state
            unset(static::$previousState[$project->id]);
        }
    }
}
