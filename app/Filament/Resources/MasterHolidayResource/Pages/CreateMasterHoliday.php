<?php

namespace App\Filament\Resources\MasterHolidayResource\Pages;

use App\Filament\Resources\MasterHolidayResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMasterHoliday extends CreateRecord
{
    protected static string $resource = MasterHolidayResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
