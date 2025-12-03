<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;

class ProjectArchiveService
{
    /**
     * Archive a project.
     *
     * Sets archived_at to current timestamp and archived_by to the user performing the action.
     *
     * Requirements: 1.1, 1.2
     *
     * @param Project $project The project to archive
     * @param User $user The user performing the archive action
     * @return void
     */
    public function archive(Project $project, User $user): void
    {
        $project->update([
            'archived_at' => now(),
            'archived_by' => $user->id,
        ]);
    }

    /**
     * Restore an archived project.
     *
     * Clears archived_at and archived_by fields.
     *
     * Requirements: 4.1, 4.2
     *
     * @param Project $project The project to restore
     * @return void
     */
    public function restore(Project $project): void
    {
        $project->update([
            'archived_at' => null,
            'archived_by' => null,
        ]);
    }

    /**
     * Mark a project as done.
     *
     * Sets done_at to current timestamp and optionally sets is_auto_done.
     *
     * Requirements: 2.1, 3.1
     *
     * @param Project $project The project to mark as done
     * @param bool $isAuto Whether this is an automatic done status (default: false)
     * @return void
     */
    public function markAsDone(Project $project, bool $isAuto = false): void
    {
        $project->update([
            'done_at' => now(),
            'is_auto_done' => $isAuto,
        ]);
    }

    /**
     * Remove done status from a project.
     *
     * Clears done_at and is_auto_done fields.
     *
     * Requirements: 2.3
     *
     * @param Project $project The project to unmark as done
     * @return void
     */
    public function unmarkDone(Project $project): void
    {
        $project->update([
            'done_at' => null,
            'is_auto_done' => false,
        ]);
    }

    /**
     * Check and update the done status of a project based on ticket completion.
     *
     * - If all tickets are completed, auto-mark as done
     * - If tickets are incomplete and project was auto-done, remove done status
     * - Empty projects (no tickets) do not auto-complete
     *
     * Requirements: 3.1, 3.2, 3.3, 3.4
     *
     * @param Project $project The project to check
     * @return void
     */
    public function checkAndUpdateDoneStatus(Project $project): void
    {
        // Refresh the project to get latest data
        $project->refresh();

        $allCompleted = $project->allTicketsCompleted();

        if ($allCompleted) {
            // All tickets completed - auto-mark as done if not already done
            if (!$project->is_done) {
                $this->markAsDone($project, true);
            }
        } else {
            // Not all tickets completed - remove auto-done status if applicable
            if ($project->is_done && $project->is_auto_done) {
                $this->unmarkDone($project);
            }
        }
    }
}
