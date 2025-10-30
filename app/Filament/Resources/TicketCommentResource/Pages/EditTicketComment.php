<?php

namespace App\Filament\Resources\TicketCommentResource\Pages;

use App\Filament\Resources\TicketCommentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTicketComment extends EditRecord
{
    protected static string $resource = TicketCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToTicket')
                ->label('Back to Ticket')
                ->color('success')
                ->url(fn () => route('filament.admin.resources.tickets.view', ['record' => $this->record->ticket_id]))
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['user_id'] = $record->user_id;

        if (! auth()->user()->can('update', $record)) {
            Notification::make()
                ->title('You do not have permission to edit this comment')
                ->danger()
                ->send();
            $this->redirect(route('filament.admin.resources.tickets.view', ['record' => $record->ticket_id]));

            return $record;
        }

        $record->update($data);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return route('filament.admin.resources.tickets.view', ['record' => $this->record->ticket_id]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Comment updated successfully';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_id'] = $this->record->user_id;
        $data['ticket_id'] = $this->record->ticket_id;

        return $data;
    }

    public function mount($record): void
    {
        parent::mount($record);

        if (! auth()->user()->can('update', $this->record)) {
            Notification::make()
                ->title('You do not have permission to edit this comment')
                ->danger()
                ->send();

            $this->redirect(route('filament.admin.resources.tickets.view', ['record' => $this->record->ticket_id]));
        }
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
