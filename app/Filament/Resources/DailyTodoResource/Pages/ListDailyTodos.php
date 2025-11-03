<?php

namespace App\Filament\Resources\DailyTodoResource\Pages;

use App\Filament\Resources\DailyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailyTodos extends ListRecords
{
    protected static string $resource = DailyTodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New To-Do')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->size('lg')
                ->keyBindings(['mod+c'])
                ->tooltip('Create a new daily to-do item (Ctrl/Cmd + C)'),
        ];
    }
}
