<?php

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDocument;
use App\Models\Hris\EmployeeMovement;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);
});

test('employee belongs to user', function () {
    expect($this->employee->user)->toBeInstanceOf(User::class);
    expect($this->employee->user->id)->toBe($this->user->id);
});

test('employee can have multiple positions', function () {
    $department = Department::factory()->create();
    $position = Position::factory()->create();
    
    EmployeePosition::factory()->create([
        'employee_id' => $this->employee->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'is_primary' => true,
    ]);
    
    EmployeePosition::factory()->create([
        'employee_id' => $this->employee->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'is_primary' => false,
    ]);

    expect($this->employee->positions)->toHaveCount(2);
});

test('employee can have primary position', function () {
    $department = Department::factory()->create();
    $position = Position::factory()->create();
    
    $primaryPosition = EmployeePosition::factory()->create([
        'employee_id' => $this->employee->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => now()->subYear(),
    ]);
    
    EmployeePosition::factory()->create([
        'employee_id' => $this->employee->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'is_primary' => false,
    ]);

    expect($this->employee->primaryPosition)->not->toBeNull();
    expect($this->employee->primaryPosition->id)->toBe($primaryPosition->id);
});

test('employee can have documents', function () {
    EmployeeDocument::factory()->count(3)->create([
        'employee_id' => $this->employee->id,
    ]);

    expect($this->employee->documents)->toHaveCount(3);
    expect($this->employee->documents->first())->toBeInstanceOf(EmployeeDocument::class);
});

test('employee can have movements', function () {
    EmployeeMovement::factory()->count(2)->create([
        'employee_id' => $this->employee->id,
    ]);

    expect($this->employee->movements)->toHaveCount(2);
});

test('employee can have leave balances', function () {
    $leaveType = \App\Models\Hris\LeaveType::factory()->create();
    
    LeaveBalance::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    expect($this->employee->leaveBalances)->toHaveCount(1);
});

test('employee can have leave requests', function () {
    $leaveType = \App\Models\Hris\LeaveType::factory()->create();
    
    LeaveRequest::factory()->count(3)->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    expect($this->employee->leaveRequests)->toHaveCount(3);
});

test('employee active scope filters correctly', function () {
    $activeEmployee = Employee::factory()->create(['is_active' => true]);
    $inactiveEmployee = Employee::factory()->create(['is_active' => false]);

    $activeEmployees = Employee::active()->get();

    expect($activeEmployees->contains($activeEmployee))->toBeTrue();
    expect($activeEmployees->contains($inactiveEmployee))->toBeFalse();
});

