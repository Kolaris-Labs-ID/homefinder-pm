<?php

namespace App\Filament\Resources\ClientVisitResource\Pages;

use App\Filament\Resources\ClientVisitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientVisits extends ListRecords
{
    protected static string $resource = ClientVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
