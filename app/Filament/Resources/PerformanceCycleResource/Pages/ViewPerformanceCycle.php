<?php

namespace App\Filament\Resources\PerformanceCycleResource\Pages;

use App\Filament\Resources\PerformanceCycleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceCycle extends ViewRecord
{
    protected static string $resource = PerformanceCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
