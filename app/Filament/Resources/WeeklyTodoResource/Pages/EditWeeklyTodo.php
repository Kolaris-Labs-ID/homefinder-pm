<?php

namespace App\Filament\Resources\WeeklyTodoResource\Pages;

use App\Filament\Resources\WeeklyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWeeklyTodo extends EditRecord
{
    protected static string $resource = WeeklyTodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->status === 'done' && $this->record->wasChanged('status')) {
            $this->dispatch('confetti');
        }
    }
}

