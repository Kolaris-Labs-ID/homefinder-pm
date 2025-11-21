<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveBalanceService;
use Carbon\Carbon;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
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

    private function calculateTotalDays(array $data): int
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        return $start->diffInDays($end) + 1;
    }
}

