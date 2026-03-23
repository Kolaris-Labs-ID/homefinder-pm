<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Hris\Position;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\LeaveWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->leaveWorkflowService = app(LeaveWorkflowService::class);
    $this->approvalWorkflowService = app(ApprovalWorkflowService::class);
    $this->leaveType = LeaveType::factory()->create();

    // Create roles for role-based approval
    $this->hrAdminRole = Role::create(['name' => 'hr-admin', 'guard_name' => 'web']);
    $this->hrStaffRole = Role::create(['name' => 'hr-staff', 'guard_name' => 'web']);

    // Create users
    $this->employeeUser = User::factory()->create(['name' => 'Employee']);
    $this->employee = Employee::factory()->create(['user_id' => $this->employeeUser->id]);

    $this->managerUser = User::factory()->create(['name' => 'Manager']);
    $this->managerEmployee = Employee::factory()->create(['user_id' => $this->managerUser->id]);

    $this->hrAdminUser = User::factory()->create(['name' => 'HR Admin']);
    $this->hrAdminUser->assignRole($this->hrAdminRole);

    // Setup employee position with manager
    $department = Department::factory()->create();
    $position = Position::factory()->create();
    EmployeePosition::factory()->create([
        'employee_id' => $this->employee->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'manager_id' => $this->managerEmployee->id,
        'is_primary' => true,
    ]);
});

test('it can approve leave request through multi-step workflow', function () {
    // 1. Create a 2-step workflow for Leave module
    $workflow = ApprovalWorkflow::create([
        'name' => 'Test Leave Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $this->managerUser->id, 'label' => 'Manager Approval'],
            ['order' => 2, 'type' => 'role', 'value' => 'hr-admin', 'label' => 'HR Approval'],
        ],
    ]);

    // 2. Create leave request and initialize workflow
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->leaveWorkflowService->initializeWorkflow($leaveRequest);
    $leaveRequest->refresh();

    // Verify initial state
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
    expect($leaveRequest->current_approver_id)->toBeNull();
    expect($leaveRequest->approvalSteps)->toHaveCount(2);

    $step1 = $leaveRequest->approvalSteps()->where('step_order', 1)->first();
    $step2 = $leaveRequest->approvalSteps()->where('step_order', 2)->first();

    expect($step1->status)->toBe(ApprovalStep::STATUS_PENDING);
    expect($step1->approver_id)->toBe($this->managerUser->id);
    expect($step2->status)->toBe(ApprovalStep::STATUS_PENDING); // Also pending because parallel

    // 3. Approve first step (Manager)
    $this->approvalWorkflowService->approveParallel($step1, $this->managerUser, 'Manager approved');

    $leaveRequest->refresh();
    $step1->refresh();
    $step2->refresh();

    expect($step1->status)->toBe(ApprovalStep::STATUS_APPROVED);
    expect($step2->status)->toBe(ApprovalStep::STATUS_SKIPPED);
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->approved_at)->not->toBeNull();
});

test('it rejects leave request if any step is rejected', function () {
    // 1. Create a 2-step workflow
    $workflow = ApprovalWorkflow::create([
        'name' => 'Test Leave Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $this->managerUser->id, 'label' => 'Manager Approval'],
            ['order' => 2, 'type' => 'role', 'value' => 'hr-admin', 'label' => 'HR Approval'],
        ],
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->leaveWorkflowService->initializeWorkflow($leaveRequest);
    
    $step1 = $leaveRequest->approvalSteps()->where('step_order', 1)->first();

    // 2. Reject first step
    $this->approvalWorkflowService->reject($step1, $this->managerUser, 'Not allowed');

    $leaveRequest->refresh();
    $step1->refresh();

    expect($step1->status)->toBe(ApprovalStep::STATUS_REJECTED);
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_REJECTED);
    expect($leaveRequest->rejection_reason)->toBe('Not allowed');
    expect($leaveRequest->rejected_at)->not->toBeNull();
});

test('it falls back to default approval when no active workflow exists', function () {
    // Ensure no active workflow for leave
    ApprovalWorkflow::where('for_module', ApprovalWorkflow::MODULE_LEAVE)->update(['is_active' => false]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->leaveWorkflowService->initializeWorkflow($leaveRequest);
    $leaveRequest->refresh();

    // Should use default behavior (manager)
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
    expect($leaveRequest->current_approver_id)->toBe($this->managerUser->id);
    expect($leaveRequest->approvalSteps)->toHaveCount(1);
    
    $step = $leaveRequest->approvalSteps->first();
    expect($step->workflow_id)->toBeNull();
    expect($step->approver_id)->toBe($this->managerUser->id);
});

test('unauthorized users cannot approve a step', function () {
    $workflow = ApprovalWorkflow::create([
        'name' => 'Single Step Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $this->managerUser->id, 'label' => 'Manager Approval'],
        ],
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->leaveWorkflowService->initializeWorkflow($leaveRequest);
    $step = $leaveRequest->approvalSteps->first();

    // Create a random user
    $randomUser = User::factory()->create();

    expect(fn() => $this->approvalWorkflowService->approveParallel($step, $randomUser))
        ->toThrow(\Exception::class, 'You are not authorized to approve this step.');
});

test('it correctly resolves role-based approvers', function () {
    $workflow = ApprovalWorkflow::create([
        'name' => 'Role Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'role', 'value' => 'hr-admin', 'label' => 'HR Admin Approval'],
        ],
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $this->leaveWorkflowService->initializeWorkflow($leaveRequest);
    $step = $leaveRequest->approvalSteps->first();

    expect($step->approver_role)->toBe('hr-admin');
    // should have resolved to $this->hrAdminUser because they have the role
    expect($step->approver_id)->toBe($this->hrAdminUser->id);
});
