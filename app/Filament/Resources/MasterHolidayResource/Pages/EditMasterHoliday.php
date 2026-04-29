<?php

namespace App\Filament\Resources\MasterHolidayResource\Pages;

use App\Filament\Resources\MasterHolidayResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMasterHoliday extends EditRecord
{
    protected static string $resource = MasterHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
