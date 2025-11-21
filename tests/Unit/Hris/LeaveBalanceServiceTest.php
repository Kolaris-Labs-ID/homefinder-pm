<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('awards annual leave after one year tenure', function () {
    $employee = Employee::factory()->create([
        'hire_date' => now()->subYears(2),
    ]);

    $leaveType = LeaveType::factory()->create([
        'code' => 'ANNUAL',
        'default_annual_quota' => 12,
    ]);

    $service = app(LeaveBalanceService::class);

    $balance = $service->ensureBalance($employee, $leaveType, now()->year);

    expect($balance->starting_balance)->toBe(12)
        ->and($balance->remaining_days)->toBe(12);
});

it('consumes_and_releases_leave_balance', function () {
    $employee = Employee::factory()->create([
        'hire_date' => now()->subYears(2),
    ]);

    $leaveType = LeaveType::factory()->create([
        'code' => 'ANNUAL',
        'default_annual_quota' => 12,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addWeek(),
        'end_date' => now()->addWeek()->addDays(2),
        'total_days' => 3,
    ]);

    $service = app(LeaveBalanceService::class);
    $service->consume($leaveRequest);

    $balance = $employee->leaveBalances()->first();
    expect($balance->used_days)->toBe(3)
        ->and($balance->remaining_days)->toBe(9);

    $service->release($leaveRequest);

    $balance->refresh();
    expect($balance->used_days)->toBe(0)
        ->and($balance->remaining_days)->toBe(12);
});

