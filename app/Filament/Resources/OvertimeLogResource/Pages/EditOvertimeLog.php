<?php

namespace App\Filament\Resources\OvertimeLogResource\Pages;

use App\Filament\Resources\OvertimeLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOvertimeLog extends EditRecord
{
    protected static string $resource = OvertimeLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
