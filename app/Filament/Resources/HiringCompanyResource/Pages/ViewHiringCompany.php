<?php

namespace App\Filament\Resources\HiringCompanyResource\Pages;

use App\Filament\Resources\HiringCompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewHiringCompany extends ViewRecord
{
    protected static string $resource = HiringCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
