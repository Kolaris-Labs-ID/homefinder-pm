<?php

namespace App\Services\Hris;

use App\Exceptions\InsufficientLeaveBalanceException;
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
    public const CODE_SICK = 'SICK';

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
        if ($leaveType->code === self::CODE_UNPAID) {
            return $this->annualLeaveExhausted($employee, $startDate->year);
        }

        // Sick leave has no quota: eligibility is gated solely on the doctor's letter
        // attachment requirement, which is enforced at the controller level.
        if ($leaveType->code === self::CODE_SICK) {
            return true;
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

        // Sick leave has no quota check.
        if ($leaveType->code === self::CODE_SICK) {
            return null;
        }

        $balance = $this->ensureBalance($employee, $leaveType, $startDate->year);

        if ($balance->remaining_days < $days) {
            return "Insufficient leave balance. You have {$balance->remaining_days} days remaining but requested {$days} days.";
        }

        return null;
    }

    public function annualLeaveRemaining(Employee $employee, int $year): int
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

    /**
     * Deduct a leave request's days from the employee's balance.
     *
     * Must be called from within a transaction owned by the caller (see
     * ApprovalWorkflowService::approve()/approveParallel()) so the lock taken
     * here actually serializes concurrent approvals instead of releasing early.
     *
     * @throws InsufficientLeaveBalanceException if a quota-tracked type (i.e. not
     *         UNPAID/SICK, which are intentionally unlimited) would go negative —
     *         this re-validates at approval time to close the gap left by
     *         canConsume() only being checked once, at submission time.
     */
    public function consume(LeaveRequest $leaveRequest): void
    {
        // Ensure we have the relationships loaded
        $leaveRequest->loadMissing(['employee', 'leaveType']);

        $year = $leaveRequest->start_date->year;

        // Lock the balance row so two overlapping requests approved at nearly the
        // same time can't both read the same remaining_days and jointly overdraw it.
        $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $startingBalance = $this->determineStartingBalance($leaveRequest->employee, $leaveRequest->leaveType, $year);

            $balance = LeaveBalance::create([
                'employee_id' => $leaveRequest->employee_id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'year' => $year,
                'starting_balance' => $startingBalance,
                'used_days' => 0,
                'remaining_days' => $startingBalance,
            ]);
        }

        $newRemaining = $balance->remaining_days - $leaveRequest->total_days;

        $isUnlimitedType = in_array($leaveRequest->leaveType->code, [self::CODE_UNPAID, self::CODE_SICK], true);

        if (! $isUnlimitedType && $newRemaining < 0) {
            throw new InsufficientLeaveBalanceException(
                "Cannot approve leave request #{$leaveRequest->id}: insufficient {$leaveRequest->leaveType->name} balance ".
                "({$balance->remaining_days} remaining, {$leaveRequest->total_days} requested)."
            );
        }

        $balance->used_days += $leaveRequest->total_days;
        $balance->remaining_days = $newRemaining;
        $balance->save();
    }

    public function release(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing(['employee', 'leaveType']);

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

        if ($leaveType->code === self::CODE_SICK) {
            // Sick leave has no quota; balance row only tracks total used days per year.
            return 0;
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
