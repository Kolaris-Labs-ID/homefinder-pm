<?php

namespace App\Filament\Resources\PayrollSettingResource\Pages;

use App\Filament\Resources\PayrollSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollSetting extends CreateRecord
{
    protected static string $resource = PayrollSettingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
