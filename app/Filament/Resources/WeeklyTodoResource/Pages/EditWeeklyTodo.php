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
}

