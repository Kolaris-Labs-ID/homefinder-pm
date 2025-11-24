<?php

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Hris\Position;
use App\Models\User;
use App\Services\Hris\LeaveWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(LeaveWorkflowService::class);
    $this->leaveType = LeaveType::factory()->create();
});

test('initializes workflow with manager as approver', function () {
    $managerUser = User::factory()->create(['name' => 'Manager']);
    $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);
    
    $employeeUser = User::factory()->create(['name' => 'Employee']);
    $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);
    
    $department = Department::factory()->create();
    $position = Position::factory()->create();
    
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'manager_id' => $managerEmployee->id,
        'is_primary' => true,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->service->initializeWorkflow($leaveRequest);

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
    expect($leaveRequest->current_approver_id)->toBe($managerUser->id);
    expect($leaveRequest->submitted_at)->not->toBeNull();
});

test('initializes workflow with hr-admin as approver when no manager', function () {
    $hrAdminUser = User::factory()->create(['name' => 'HR Admin']);
    $hrAdminRole = Role::create(['name' => 'hr-admin', 'guard_name' => 'web']);
    $hrAdminUser->assignRole($hrAdminRole);
    
    $employeeUser = User::factory()->create(['name' => 'Employee']);
    $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->service->initializeWorkflow($leaveRequest);

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
    expect($leaveRequest->current_approver_id)->toBe($hrAdminUser->id);
});

test('initializes workflow with hr-staff as approver when no hr-admin', function () {
    $hrStaffUser = User::factory()->create(['name' => 'HR Staff']);
    $hrStaffRole = Role::create(['name' => 'hr-staff', 'guard_name' => 'web']);
    $hrStaffUser->assignRole($hrStaffRole);
    
    $employeeUser = User::factory()->create(['name' => 'Employee']);
    $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->service->initializeWorkflow($leaveRequest);

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
    expect($leaveRequest->current_approver_id)->toBe($hrStaffUser->id);
});

test('does not assign employee as their own approver', function () {
    $employeeUser = User::factory()->create(['name' => 'Employee']);
    $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);
    
    $department = Department::factory()->create();
    $position = Position::factory()->create();
    
    // Employee is their own manager (should not happen but test edge case)
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'manager_id' => $employee->id,
        'is_primary' => true,
    ]);

    $hrAdminUser = User::factory()->create(['name' => 'HR Admin']);
    $hrAdminRole = Role::create(['name' => 'hr-admin', 'guard_name' => 'web']);
    $hrAdminUser->assignRole($hrAdminRole);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->service->initializeWorkflow($leaveRequest);

    $leaveRequest->refresh();
    // Should fallback to HR admin, not assign employee as approver
    expect($leaveRequest->current_approver_id)->toBe($hrAdminUser->id);
    expect($leaveRequest->current_approver_id)->not->toBe($employeeUser->id);
});

