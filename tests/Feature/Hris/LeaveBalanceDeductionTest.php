<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\User;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->leaveBalanceService = app(LeaveBalanceService::class);

    // Create an annual leave type
    $this->annualLeaveType = LeaveType::factory()->create([
        'code' => 'ANNUAL',
        'name' => 'Annual Leave',
        'default_annual_quota' => 12,
    ]);

    // Create employee
    $this->user = User::factory()->create(['name' => 'John Doe']);
    $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);
});

test('leave balance is decreased when annual leave request is approved', function () {
    $year = now()->year;

    // Ensure starting balance of 12 days
    $balance = LeaveBalance::factory()->create([
        'employee_id'     => $this->employee->id,
        'leave_type_id'   => $this->annualLeaveType->id,
        'year'            => $year,
        'starting_balance' => 12,
        'used_days'       => 0,
        'remaining_days'  => 12,
    ]);

    // Create a leave request for 3 days
    $start = now()->addWeek()->startOfDay();
    $end   = $start->copy()->addDays(2);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id'   => $this->employee->id,
        'leave_type_id' => $this->annualLeaveType->id,
        'start_date'    => $start,
        'end_date'      => $end,
        'total_days'    => 3,
        'status'        => LeaveRequest::STATUS_PENDING,
    ]);

    // Approve the leave (triggers consume internally)
    $leaveRequest->markAsApproved();
    $balance->refresh();

    expect($leaveRequest->fresh()->status)->toBe(LeaveRequest::STATUS_APPROVED)
        ->and($balance->used_days)->toBe(3)
        ->and($balance->remaining_days)->toBe(9);
});

test('used_days accumulates correctly across multiple approved leave requests', function () {
    $year = now()->year;

    $balance = LeaveBalance::factory()->create([
        'employee_id'      => $this->employee->id,
        'leave_type_id'    => $this->annualLeaveType->id,
        'year'             => $year,
        'starting_balance' => 12,
        'used_days'        => 0,
        'remaining_days'   => 12,
    ]);

    // First leave: 2 days
    $leave1 = LeaveRequest::factory()->create([
        'employee_id'   => $this->employee->id,
        'leave_type_id' => $this->annualLeaveType->id,
        'start_date'    => now()->addWeek(),
        'end_date'      => now()->addWeek()->addDay(),
        'total_days'    => 2,
        'status'        => LeaveRequest::STATUS_PENDING,
    ]);
    $leave1->markAsApproved();
    $balance->refresh();

    expect($balance->used_days)->toBe(2)
        ->and($balance->remaining_days)->toBe(10);

    // Second leave: 3 days
    $leave2 = LeaveRequest::factory()->create([
        'employee_id'   => $this->employee->id,
        'leave_type_id' => $this->annualLeaveType->id,
        'start_date'    => now()->addWeeks(2),
        'end_date'      => now()->addWeeks(2)->addDays(2),
        'total_days'    => 3,
        'status'        => LeaveRequest::STATUS_PENDING,
    ]);
    $leave2->markAsApproved();
    $balance->refresh();

    expect($balance->used_days)->toBe(5)
        ->and($balance->remaining_days)->toBe(7);
});

test('canConsume returns false when remaining balance is insufficient', function () {
    $start = now()->addWeek();

    $balance = LeaveBalance::factory()->create([
        'employee_id'      => $this->employee->id,
        'leave_type_id'    => $this->annualLeaveType->id,
        'year'             => $start->year,
        'starting_balance' => 12,
        'used_days'        => 10,
        'remaining_days'   => 2,
    ]);

    // 5 days requested but only 2 remaining
    $canConsume = $this->leaveBalanceService->canConsume(
        $this->employee,
        $this->annualLeaveType,
        5,
        $start
    );

    expect($canConsume)->toBeFalse();
});

test('canConsume returns true when remaining balance is sufficient', function () {
    $start = now()->addWeek();

    $balance = LeaveBalance::factory()->create([
        'employee_id'      => $this->employee->id,
        'leave_type_id'    => $this->annualLeaveType->id,
        'year'             => $start->year,
        'starting_balance' => 12,
        'used_days'        => 0,
        'remaining_days'   => 12,
    ]);

    $canConsume = $this->leaveBalanceService->canConsume(
        $this->employee,
        $this->annualLeaveType,
        3,
        $start
    );

    expect($canConsume)->toBeTrue();
});

test('releasing a leave request restores the balance', function () {
    $year = now()->year;
    $start = now()->addWeek();

    $balance = LeaveBalance::factory()->create([
        'employee_id'      => $this->employee->id,
        'leave_type_id'    => $this->annualLeaveType->id,
        'year'             => $year,
        'starting_balance' => 12,
        'used_days'        => 5,
        'remaining_days'   => 7,
    ]);

    // A previously approved leave with 5 days
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id'   => $this->employee->id,
        'leave_type_id' => $this->annualLeaveType->id,
        'start_date'    => $start,
        'end_date'      => $start->copy()->addDays(4),
        'total_days'    => 5,
        'status'        => LeaveRequest::STATUS_APPROVED,
    ]);

    // Release (e.g. leave is cancelled)
    $this->leaveBalanceService->release($leaveRequest);
    $balance->refresh();

    expect($balance->used_days)->toBe(0)
        ->and($balance->remaining_days)->toBe(12);
});
