<?php

namespace App\Filament\Resources\ApprovalWorkflowResource\Pages;

use App\Filament\Resources\ApprovalWorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApprovalWorkflow extends EditRecord
{
    protected static string $resource = ApprovalWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
