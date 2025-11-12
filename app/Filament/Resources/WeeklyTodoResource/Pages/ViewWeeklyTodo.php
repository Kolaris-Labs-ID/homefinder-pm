<?php

namespace App\Filament\Resources\WeeklyTodoResource\Pages;

use App\Filament\Resources\WeeklyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWeeklyTodo extends ViewRecord
{
    protected static string $resource = WeeklyTodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

