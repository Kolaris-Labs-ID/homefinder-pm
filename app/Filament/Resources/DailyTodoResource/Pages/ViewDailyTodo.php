<?php

namespace App\Filament\Resources\DailyTodoResource\Pages;

use App\Filament\Resources\DailyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDailyTodo extends ViewRecord
{
    protected static string $resource = DailyTodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
