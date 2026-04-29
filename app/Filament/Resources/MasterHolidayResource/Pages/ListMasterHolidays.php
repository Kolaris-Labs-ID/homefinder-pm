<?php

namespace App\Filament\Resources\MasterHolidayResource\Pages;

use App\Filament\Resources\MasterHolidayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMasterHolidays extends ListRecords
{
    protected static string $resource = MasterHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
