<?php

namespace App\Filament\Resources\PayrollSettingResource\Pages;

use App\Filament\Resources\PayrollSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrollSetting extends ViewRecord
{
    protected static string $resource = PayrollSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
