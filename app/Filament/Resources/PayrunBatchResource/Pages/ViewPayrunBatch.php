<?php

namespace App\Filament\Resources\PayrunBatchResource\Pages;

use App\Filament\Resources\PayrunBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrunBatch extends ViewRecord
{
    protected static string $resource = PayrunBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }
}
