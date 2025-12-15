<?php

namespace App\Filament\Resources\PayrunBatchResource\Pages;

use App\Filament\Resources\PayrunBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrunBatches extends ListRecords
{
    protected static string $resource = PayrunBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
