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

        // Check annual leave eligibility (must be employed for at least 1 year)
        // Skip this check for non-annual leave types
        if ($leaveType->code === 'ANNUAL' && ! $this->hasAnnualLeaveEligibility($employee, $startDate)) {
            \Log::info('Annual leave eligibility check failed', [
                'employee_id' => $employee->id,
                'hire_date' => $employee->hire_date,
                'start_date' => $startDate,
                'eligible_from' => $employee->hire_date?->copy()->addYear(),
            ]);
            return false;
        }

        return $balance->remaining_days >= $days;
    }

    /**
     * Get the reason why an employee cannot consume leave.
     */
    public function getIneligibilityReason(Employee $employee, LeaveType $leaveType, int $days, Carbon $startDate): ?string
    {
        $balance = $this->ensureBalance($employee, $leaveType, $startDate->year);

        if ($leaveType->code === 'ANNUAL' && ! $this->hasAnnualLeaveEligibility($employee, $startDate)) {
            $eligibleFrom = $employee->hire_date?->copy()->addYear()->format('d M Y');
            return "You are not eligible for Annual Leave yet. You will be eligible from {$eligibleFrom} (after 1 year of employment).";
        }

        if ($balance->remaining_days < $days) {
            return "Insufficient leave balance. You have {$balance->remaining_days} days remaining but requested {$days} days.";
        }

        return null;
    }

    public function consume(LeaveRequest $leaveRequest): void
    {
        // Ensure we have the relationships loaded
        $leaveRequest->loadMissing(['employee', 'leaveType']);

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
        if (! $employee->hire_date) {
            return false;
        }

        // Employee must be employed for at least 1 year to be eligible for annual leave
        // For testing/development, you can set this to return true
        // return true;

        return $employee->hire_date->copy()->addYear()->startOfDay() <= $asOfDate->copy()->startOfDay();
    }
}

