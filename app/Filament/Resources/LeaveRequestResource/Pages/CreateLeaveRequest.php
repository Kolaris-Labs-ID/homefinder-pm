<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveBalanceService;
use App\Services\Hris\LeaveWorkflowService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['total_days'] = $this->calculateTotalDays($data);

        $employee = Employee::findOrFail($data['employee_id']);
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $startDate = Carbon::parse($data['start_date']);

        $balanceService = app(LeaveBalanceService::class);

        if (! $balanceService->canConsume($employee, $leaveType, $data['total_days'], $startDate)) {
            throw ValidationException::withMessages([
                'total_days' => 'Insufficient leave balance or not yet eligible for this leave type.',
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(LeaveWorkflowService::class)->initializeWorkflow($this->record);
    }

    private function calculateTotalDays(array $data): int
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        return $start->diffInDays($end) + 1;
    }
}

