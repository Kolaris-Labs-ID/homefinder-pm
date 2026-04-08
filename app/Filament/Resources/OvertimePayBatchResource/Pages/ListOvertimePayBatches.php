<?php

namespace App\Filament\Resources\OvertimePayBatchResource\Pages;

use App\Filament\Resources\OvertimePayBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOvertimePayBatches extends ListRecords
{
    protected static string $resource = OvertimePayBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
