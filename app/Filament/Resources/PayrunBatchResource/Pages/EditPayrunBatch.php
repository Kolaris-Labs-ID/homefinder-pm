<?php

namespace App\Filament\Resources\PayrunBatchResource\Pages;

use App\Filament\Resources\PayrunBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrunBatch extends EditRecord
{
    protected static string $resource = PayrunBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
