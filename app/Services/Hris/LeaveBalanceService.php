<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    public const CODE_UNPAID = 'UNPAID';
    public const CODE_ANNUAL = 'ANNUAL';

    public function ensureBalance(Employee $employee, LeaveType $leaveType, ?int $year = null): LeaveBalance
    {
        $year ??= Carbon::now()->year;

        // Unpaid leave has no tracked balance; return a transient zero balance
        // so callers can read remaining_days without persisting a row.
        if ($leaveType->code === self::CODE_UNPAID) {
            return new LeaveBalance([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
                'starting_balance' => 0,
                'used_days' => 0,
                'remaining_days' => 0,
            ]);
        }

        $startingBalance = $this->determineStartingBalance($employee, $leaveType, $year);

        return LeaveBalance::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ],
            [
                'starting_balance' => $startingBalance,
                'used_days' => 0,
                'remaining_days' => $startingBalance,
            ]
        );
    }

    public function canConsume(Employee $employee, LeaveType $leaveType, int $days, Carbon $startDate): bool
    {
        if ($leaveType->code === self::CODE_UNPAID) {
            return $this->annualLeaveExhausted($employee, $startDate->year);
        }

        $balance = $this->ensureBalance($employee, $leaveType, $startDate->year);

        return $balance->remaining_days >= $days;
    }

    /**
     * Get the reason why an employee cannot consume leave.
     */
    public function getIneligibilityReason(Employee $employee, LeaveType $leaveType, int $days, Carbon $startDate): ?string
    {
        if ($leaveType->code === self::CODE_UNPAID) {
            if ($this->annualLeaveExhausted($employee, $startDate->year)) {
                return null;
            }

            $annualRemaining = $this->annualLeaveRemaining($employee, $startDate->year);

            return "Unpaid Leave is only available after your annual leave quota is exhausted. You still have {$annualRemaining} day(s) of annual leave remaining.";
        }

        $balance = $this->ensureBalance($employee, $leaveType, $startDate->year);

        if ($balance->remaining_days < $days) {
            return "Insufficient leave balance. You have {$balance->remaining_days} days remaining but requested {$days} days.";
        }

        return null;
    }

    protected function annualLeaveRemaining(Employee $employee, int $year): int
    {
        $annualType = LeaveType::where('code', self::CODE_ANNUAL)->first();

        if (! $annualType) {
            return 0;
        }

        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $annualType->id)
            ->where('year', $year)
            ->first();

        return (int) ($balance?->remaining_days ?? 0);
    }

    protected function annualLeaveExhausted(Employee $employee, int $year): bool
    {
        return $this->annualLeaveRemaining($employee, $year) <= 0;
    }

    public function consume(LeaveRequest $leaveRequest): void
    {
        // Ensure we have the relationships loaded
        $leaveRequest->loadMissing(['employee', 'leaveType']);

        // Unpaid leave does not consume any tracked balance.
        if ($leaveRequest->leaveType && $leaveRequest->leaveType->code === self::CODE_UNPAID) {
            return;
        }

        $year = $leaveRequest->start_date->year;

        // Get or create the balance
        $balance = LeaveBalance::firstOrCreate(
            [
                'employee_id' => $leaveRequest->employee_id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'year' => $year,
            ],
            [
                'starting_balance' => $this->determineStartingBalance(
                    $leaveRequest->employee,
                    $leaveRequest->leaveType,
                    $year
                ),
                'used_days' => 0,
                'remaining_days' => $this->determineStartingBalance(
                    $leaveRequest->employee,
                    $leaveRequest->leaveType,
                    $year
                ),
            ]
        );

        // Update the balance
        $balance->used_days = $balance->used_days + $leaveRequest->total_days;
        $balance->remaining_days = $balance->remaining_days - $leaveRequest->total_days;
        $balance->save();
    }

    public function release(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing(['employee', 'leaveType']);

        // Unpaid leave does not track balance; nothing to release.
        if ($leaveRequest->leaveType && $leaveRequest->leaveType->code === self::CODE_UNPAID) {
            return;
        }

        DB::transaction(function () use ($leaveRequest) {
            $balance = $this->ensureBalance(
                $leaveRequest->employee,
                $leaveRequest->leaveType,
                $leaveRequest->start_date->year
            );

            $balance->update([
                'used_days' => max(0, $balance->used_days - $leaveRequest->total_days),
                'remaining_days' => $balance->remaining_days + $leaveRequest->total_days,
            ]);
        });
    }

    protected function determineStartingBalance(Employee $employee, LeaveType $leaveType, int $year): int
    {
        if ($leaveType->code === self::CODE_UNPAID) {
            return 0;
        }

        if ($leaveType->code === self::CODE_ANNUAL) {
            return 12;
        }

        if ($leaveType->code === 'SICK') {
            return 14; // 14 days sick leave per year
        }

        if ($leaveType->code === 'PATERNITY') {
            return 3;
        }

        if ($leaveType->code === 'MATERNITY') {
            return 84; // 6 weeks before + 6 weeks after (total days)
        }

        return $leaveType->default_annual_quota ?? 0;
    }

    protected function hasAnnualLeaveEligibility(Employee $employee, Carbon $asOfDate): bool
    {
        // All employees are eligible for annual leave regardless of tenure
        return true;
    }
}
