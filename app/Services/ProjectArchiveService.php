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
     * @param  Project  $project  The project to archive
     * @param  User  $user  The user performing the archive action
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
     * @param  Project  $project  The project to restore
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
     * @param  Project  $project  The project to mark as done
     * @param  bool  $isAuto  Whether this is an automatic done status (default: false)
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
     * @param  Project  $project  The project to unmark as done
     */
    public function unmarkDone(Project $project): void
    {
        $project->update([
            'done_at' => null,
            'is_auto_done' => false,
        ]);
    }

    /**
     * Archive all tickets in a project.
     *
     * Called when a project is archived or marked as done.
     * Only archives tickets that are not already archived.
     *
     * @param  Project  $project  The project whose tickets should be archived
     * @param  User|null  $user  The user performing the action (optional, uses project's archived_by or auth user)
     * @return int Number of tickets archived
     */
    public function archiveProjectTickets(Project $project, ?User $user = null): int
    {
        $userId = $user?->id ?? $project->archived_by ?? auth()->id();

        return $project->tickets()
            ->whereNull('archived_at')
            ->update([
                'archived_at' => now(),
                'archived_by' => $userId,
            ]);
    }

    /**
     * Restore all archived tickets in a project.
     *
     * Called when a project is restored from archived/done status.
     *
     * @param  Project  $project  The project whose tickets should be restored
     * @return int Number of tickets restored
     */
    public function restoreProjectTickets(Project $project): int
    {
        return $project->tickets()
            ->whereNotNull('archived_at')
            ->update([
                'archived_at' => null,
                'archived_by' => null,
            ]);
    }

    /**
     * Handle project status change for ticket archiving.
     *
     * Archives tickets when project is archived or done.
     * Restores tickets when project is restored from archived/done.
     *
     * @param  Project  $project  The project that changed status
     * @param  bool  $wasArchived  Previous archived status
     * @param  bool  $wasDone  Previous done status
     */
    public function handleProjectStatusChange(Project $project, bool $wasArchived, bool $wasDone): void
    {
        $isNowArchivedOrDone = $project->is_archived || $project->is_done;
        $wasArchivedOrDone = $wasArchived || $wasDone;

        if ($isNowArchivedOrDone && ! $wasArchivedOrDone) {
            // Project just became archived or done - archive all tickets
            $this->archiveProjectTickets($project);
        } elseif (! $isNowArchivedOrDone && $wasArchivedOrDone) {
            // Project just became active again - restore all tickets
            $this->restoreProjectTickets($project);
        }
    }
}
