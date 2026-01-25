<?php

namespace App\Filament\Resources\PayrollSettingResource\Pages;

use App\Filament\Resources\PayrollSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollSetting extends EditRecord
{
    protected static string $resource = PayrollSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
