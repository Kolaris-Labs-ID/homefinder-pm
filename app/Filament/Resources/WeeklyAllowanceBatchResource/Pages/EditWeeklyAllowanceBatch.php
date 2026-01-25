<?php

namespace App\Filament\Resources\WeeklyAllowanceBatchResource\Pages;

use App\Filament\Resources\WeeklyAllowanceBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWeeklyAllowanceBatch extends EditRecord
{
    protected static string $resource = WeeklyAllowanceBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
