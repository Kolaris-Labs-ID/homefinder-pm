<?php

namespace App\Filament\Resources\HiringCompanyResource\Pages;

use App\Filament\Resources\HiringCompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHiringCompany extends EditRecord
{
    protected static string $resource = HiringCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
