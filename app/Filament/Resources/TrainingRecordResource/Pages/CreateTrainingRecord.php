<?php

namespace App\Filament\Resources\TrainingRecordResource\Pages;

use App\Filament\Resources\TrainingRecordResource;
use App\Models\Hris\ApprovalWorkflow;
use App\Services\Hris\ApprovalWorkflowService;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainingRecord extends CreateRecord
{
    protected static string $resource = TrainingRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $canManageOthers = $user?->can('manage_training_record') || $user?->hasRole(['super_admin', 'hr_manager']);

        if (! $canManageOthers && $user?->employee) {
            $data['employee_id'] = $user->employee->id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(ApprovalWorkflowService::class)->initializeWorkflow($this->record, ApprovalWorkflow::MODULE_TRAINING);
    }
}
