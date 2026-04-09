<?php

namespace App\Filament\Resources\LatePayCompensationBatchResource\Pages;

use App\Filament\Resources\LatePayCompensationBatchResource;
use App\Models\Hris\LatePayCompensationBatch;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLatePayCompensationBatch extends EditRecord
{
    protected static string $resource = LatePayCompensationBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn (LatePayCompensationBatch $record) => $record->isEditable()),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        if ($this->record->isLocked()) {
            abort(403, 'This batch is locked and cannot be edited.');
        }
    }
}
