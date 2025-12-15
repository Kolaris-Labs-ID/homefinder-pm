<?php

namespace App\Filament\Resources\PerformanceCycleResource\Pages;

use App\Filament\Resources\PerformanceCycleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceCycle extends EditRecord
{
    protected static string $resource = PerformanceCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
