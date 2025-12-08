<?php

namespace App\Filament\Resources\ApprovalWorkflowResource\Pages;

use App\Filament\Resources\ApprovalWorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApprovalWorkflows extends ListRecords
{
    protected static string $resource = ApprovalWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
