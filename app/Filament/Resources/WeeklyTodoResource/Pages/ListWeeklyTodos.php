<?php

namespace App\Filament\Resources\WeeklyTodoResource\Pages;

use App\Filament\Resources\WeeklyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyTodos extends ListRecords
{
    protected static string $resource = WeeklyTodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Weekly To-Do')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->size('lg')
                ->keyBindings(['mod+c'])
                ->tooltip('Create a new weekly to-do item (Ctrl/Cmd + C)'),
        ];
    }
}

