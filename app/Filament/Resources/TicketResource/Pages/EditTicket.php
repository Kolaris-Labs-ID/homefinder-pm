<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Helpers\RoleHelper;
use App\Models\Project;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle assignees validation before saving
        if (!empty($data['assignees']) && !empty($data['project_id'])) {
            $project = Project::find($data['project_id']);

            if ($project) {
                $validAssignees = [];
                $invalidAssignees = [];

                // First check project membership
                foreach ($data['assignees'] as $userId) {
                    $isMember = $project->members()->where('users.id', $userId)->exists();

                    if ($isMember) {
                        $validAssignees[] = $userId;
                    } else {
                        $invalidAssignees[] = $userId;
                    }
                }

                // Then validate role-based assignment restrictions
                $validation = RoleHelper::validateAssignments($validAssignees);
                $validAssignees = $validation['allowed'];
                $invalidAssignees = array_merge($invalidAssignees, $validation['disallowed']);

                // Update data with only valid assignees
                $data['assignees'] = $validAssignees;

                // Show warning if some users were invalid
                if (!empty($invalidAssignees)) {
                    $reasons = [];
                    if (count($invalidAssignees) > 0) {
                        $reasons[] = 'Some selected users are not members of this project or do not meet role requirements.';
                    }

                    Notification::make()
                        ->warning()
                        ->title('Some assignees removed')
                        ->body(implode(' ', $reasons) ?: 'Some selected users have been removed from assignees.')
                        ->send();
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync assignees after saving (since it's a many-to-many relationship)
        if (isset($this->data['assignees']) && is_array($this->data['assignees'])) {
            $this->record->syncAssigneesWithActivity($this->data['assignees'], Auth::user());
        }
    }

    protected function getSavedNotification(): ?Notification
    {
        $record = $this->record;

        return Notification::make()
            ->success()
            ->title('Ticket updated')
            ->body("Ticket '{$record->name}' has been updated successfully.")
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('View Ticket')
                    ->url(TicketResource::getUrl('view', ['record' => $record->id]))
                    ->close(),
            ])
            ->duration(5000);
    }
}
