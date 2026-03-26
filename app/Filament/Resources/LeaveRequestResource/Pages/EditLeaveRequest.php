<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveType;
use App\Models\User;
use App\Services\Hris\LeaveBalanceService;
use Carbon\Carbon;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $leaveRequest = $this->record->loadMissing('employee.user');
        $employeeUserId = $leaveRequest->employee?->user_id;
        $collisionUserId = User::query()->whereKey($leaveRequest->employee_id)->value('id');
        $authUser = request()->user();

        // #region agent log
        $this->debugLog('H2', 'edit mount resolved leave request', [
            'record_id' => $leaveRequest->id,
            'route_record' => $record,
            'record_employee_id' => $leaveRequest->employee_id,
            'record_employee_user_id' => $employeeUserId,
            'auth_user_id' => $authUser?->id,
            'auth_employee_id' => data_get($authUser, 'employee.id'),
        ]);
        // #endregion

        // #region agent log
        $this->debugLog('H1', 'edit mount employee id collision check', [
            'record_id' => $leaveRequest->id,
            'record_employee_id' => $leaveRequest->employee_id,
            'record_employee_user_id' => $employeeUserId,
            'user_exists_with_employee_id' => (bool) $collisionUserId,
            'collision_user_id' => $collisionUserId,
            'employee_id_matches_employee_user_id' => $leaveRequest->employee_id === $employeeUserId,
        ]);
        // #endregion
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $leaveRequest = $this->record->loadMissing('employee.user');

        // #region agent log
        $this->debugLog('H4', 'edit form data before fill', [
            'record_id' => $leaveRequest->id,
            'data_employee_id' => $data['employee_id'] ?? null,
            'record_employee_id' => $leaveRequest->employee_id,
            'record_employee_user_id' => $leaveRequest->employee?->user_id,
            'data_matches_record_employee_id' => ($data['employee_id'] ?? null) === $leaveRequest->employee_id,
            'data_matches_employee_user_id' => ($data['employee_id'] ?? null) === $leaveRequest->employee?->user_id,
        ]);
        // #endregion

        return $data;
    }

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

    private function debugLog(string $hypothesisId, string $message, array $data): void
    {
        @file_put_contents(
            '/Users/arirudiana/Devs/hris-phi/.cursor/debug-21491e.log',
            json_encode([
                'sessionId' => '21491e',
                'runId' => 'initial',
                'hypothesisId' => $hypothesisId,
                'location' => 'EditLeaveRequest.php',
                'message' => $message,
                'data' => $data,
                'timestamp' => round(microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
}

