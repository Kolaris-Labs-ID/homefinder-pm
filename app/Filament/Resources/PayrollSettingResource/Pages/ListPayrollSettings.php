<?php

namespace App\Filament\Resources\PayrollSettingResource\Pages;

use App\Filament\Resources\PayrollSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollSettings extends ListRecords
{
    protected static string $resource = PayrollSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
