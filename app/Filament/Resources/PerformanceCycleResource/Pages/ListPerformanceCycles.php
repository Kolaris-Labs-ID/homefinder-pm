<?php

namespace App\Filament\Resources\PerformanceCycleResource\Pages;

use App\Filament\Resources\PerformanceCycleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceCycles extends ListRecords
{
    protected static string $resource = PerformanceCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
