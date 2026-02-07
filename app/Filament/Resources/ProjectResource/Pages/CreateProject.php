<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Events\ProjectMemberAttached;
use App\Helpers\RoleHelper;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $createDefaultStatuses = $this->data['create_default_statuses'] ?? true;

        if ($createDefaultStatuses) {
            $defaultStatuses = [
                ['name' => 'Backlog', 'color' => '#6B7280', 'sort_order' => 0],
                ['name' => 'To Do', 'color' => '#F59E0B', 'sort_order' => 1],
                ['name' => 'In Progress', 'color' => '#3B82F6', 'sort_order' => 2],
                ['name' => 'Review', 'color' => '#8B5CF6', 'sort_order' => 3],
                ['name' => 'Done', 'color' => '#10B981', 'sort_order' => 4]
            ];

            foreach ($defaultStatuses as $status) {
                $this->record->ticketStatuses()->create($status);
            }
        }

        // Ensure the user who created the project is added as a member so they can access it
        $creatorId = auth()->id();
        if ($creatorId) {
            $this->record->members()->syncWithoutDetaching($creatorId);
        }

        // Dispatch events for members added during creation
        // Filament's relationship component will sync members automatically,
        // but we need to dispatch events for them
        $memberIds = $this->data['members'] ?? [];
        $assignedBy = auth()->user();
        
        if ($assignedBy && !empty($memberIds)) {
            foreach ($memberIds as $memberId) {
                $user = User::find($memberId);
                if ($user && $user->id !== $creatorId) {
                    // Only dispatch if user can be assigned (role validation)
                    if (RoleHelper::canAssignTo($user, $assignedBy)) {
                        ProjectMemberAttached::dispatch($this->record, $user, $assignedBy);
                    }
                }
            }
        }
        
        // Also dispatch event for creator if they weren't already in the members list
        if ($creatorId && !in_array($creatorId, $memberIds)) {
            $creator = User::find($creatorId);
            if ($creator && $assignedBy) {
                ProjectMemberAttached::dispatch($this->record, $creator, $assignedBy);
            }
        }
    }
}
