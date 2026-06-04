<?php

namespace App\Filament\Resources\HiringCompanyResource\Pages;

use App\Filament\Resources\HiringCompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHiringCompanies extends ListRecords
{
    protected static string $resource = HiringCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
