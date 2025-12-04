<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Helpers\RoleHelper;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ticket;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function fillForm(): void
    {
        $copyFromId = request()->query('copy_from');

        if ($copyFromId) {
            $ticket = Ticket::find($copyFromId);

            if ($ticket) {
                $data = $ticket->toArray();
                unset(
                    $data['id'],
                    $data['uuid'],
                    $data['created_at'],
                    $data['updated_at'],
                    $data['created_by']
                );

                $data['assignees'] = $ticket->assignees()->pluck('users.id')->toArray();
                $this->form->fill($data);

                return;
            }
        }

        parent::fillForm();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $ticket = parent::handleRecordCreation($data);

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

                if (!empty($validAssignees)) {
                    $ticket->assignees()->sync($validAssignees);
                }

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

                if (empty($validAssignees)) {
                    $currentUserIsMember = $project->members()->where('users.id', auth()->id())->exists();

                    if ($currentUserIsMember) {
                        $ticket->assignees()->sync([auth()->id()]);

                        Notification::make()
                            ->info()
                            ->title('Auto-assigned')
                            ->body('No valid assignees found. You have been automatically assigned to this ticket.')
                            ->send();
                    }
                }
            }
        } else {
            if (!empty($data['project_id'])) {
                $project = Project::find($data['project_id']);
                $currentUserIsMember = $project?->members()->where('users.id', auth()->id())->exists();

                if ($currentUserIsMember) {
                    $ticket->assignees()->sync([auth()->id()]);
                }
            }
        }

        return $ticket;
    }

    protected function getRedirectUrl(): string
    {
        $referer = request()->header('referer');

        if ($referer && str_contains($referer, 'project-board-page')) {
            return '/admin/project-board-page';
        }

        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $record = $this->record;

        return Notification::make()
            ->success()
            ->title('Ticket created')
            ->body("Ticket '{$record->name}' has been created successfully.")
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('View Ticket')
                    ->url(TicketResource::getUrl('view', ['record' => $record->id]))
                    ->close(),
            ])
            ->duration(5000);
    }
}
