<?php

namespace App\Filament\Resources\OvertimePayBatchResource\Pages;

use App\Filament\Resources\OvertimePayBatchResource;
use App\Models\Hris\OvertimePayBatch;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOvertimePayBatch extends EditRecord
{
    protected static string $resource = OvertimePayBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn (OvertimePayBatch $record) => $record->isEditable()),
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
