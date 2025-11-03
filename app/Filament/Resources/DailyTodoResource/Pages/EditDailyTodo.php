<?php

namespace App\Filament\Resources\DailyTodoResource\Pages;

use App\Filament\Resources\DailyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyTodo extends EditRecord
{
    protected static string $resource = DailyTodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
