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
    public function ensureBalance(Employee $employee, LeaveType $leaveType, ?int $year = null): LeaveBalance
    {
        $year ??= Carbon::now()->year;

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
        $balance = $this->ensureBalance($employee, $leaveType, $startDate->year);

        if ($leaveType->code === 'ANNUAL' && ! $this->hasAnnualLeaveEligibility($employee, $startDate)) {
            return false;
        }

        return $balance->remaining_days >= $days;
    }

    public function consume(LeaveRequest $leaveRequest): void
    {
        DB::transaction(function () use ($leaveRequest) {
            $balance = $this->ensureBalance(
                $leaveRequest->employee,
                $leaveRequest->leaveType,
                $leaveRequest->start_date->year
            );

            $balance->increment('used_days', $leaveRequest->total_days);
            $balance->decrement('remaining_days', $leaveRequest->total_days);
        });
    }

    public function release(LeaveRequest $leaveRequest): void
    {
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
        if ($leaveType->code === 'ANNUAL') {
            return $this->hasAnnualLeaveEligibility($employee, Carbon::create($year)) ? 12 : 0;
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
        if (! $employee->hire_date) {
            return false;
        }

        return $employee->hire_date->copy()->addYear()->startOfDay() <= $asOfDate->copy()->startOfDay();
    }
}

