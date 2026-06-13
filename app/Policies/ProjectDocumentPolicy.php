<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;

class ProjectDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_project_document');
    }

    public function view(User $user, ProjectDocument $document): bool
    {
        return $this->canAccessProject($user, $document->project)
            && $user->can('view_project_document');
    }

    public function download(User $user, ProjectDocument $document): bool
    {
        return $this->canAccessProject($user, $document->project)
            && $user->can('download_project_document');
    }

    public function create(User $user): bool
    {
        return $user->can('create_project_document');
    }

    public function delete(User $user, ProjectDocument $document): bool
    {
        return $this->canAccessProject($user, $document->project)
            && $user->can('delete_project_document');
    }

    // Same visibility rule ProjectResource::getEloquentQuery() uses: C-level/super_admin see all,
    // everyone else only projects they are a member of. Keeps document access == project access.
    private function canAccessProject(User $user, Project $project): bool
    {
        if ($user->canSeeAllPmData()) {
            return true;
        }

        return $project->members()->where('users.id', $user->id)->exists();
    }
}
