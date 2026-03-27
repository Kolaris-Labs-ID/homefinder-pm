<?php

namespace App\Filament\Resources\OvertimeLogResource\Pages;

use App\Filament\Resources\OvertimeLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOvertimeLog extends ViewRecord
{
    protected static string $resource = OvertimeLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
