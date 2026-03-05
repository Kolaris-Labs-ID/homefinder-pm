<?php

namespace App\Filament\Resources\ClientVisitResource\Pages;

use App\Filament\Resources\ClientVisitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClientVisit extends EditRecord
{
    protected static string $resource = ClientVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
