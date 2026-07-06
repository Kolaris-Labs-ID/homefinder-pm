<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStuckLeaveRequest(): LeaveRequest
{
    $manager = User::factory()->create();

    ApprovalWorkflow::create([
        'name' => 'Manager approves',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => (string) $manager->id],
        ],
    ]);

    $leaveType = LeaveType::factory()->create(['default_annual_quota' => 12]);
    $employee = Employee::factory()->create();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'total_days' => 3,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    app(\App\Services\Hris\ApprovalWorkflowService::class)
        ->initializeParallelWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    // Simulate the pre-84c5b49 data corruption directly: the step is approved but the
    // parent record's status write never ran (what an unwrapped transaction used to leave behind).
    ApprovalStep::where('approvable_id', $leaveRequest->id)
        ->where('approvable_type', LeaveRequest::class)
        ->update(['status' => ApprovalStep::STATUS_APPROVED, 'approver_id' => $manager->id]);

    return $leaveRequest;
}

test('dry-run reports stuck leave requests without writing', function () {
    $leaveRequest = makeStuckLeaveRequest();

    $this->artisan('hris:fix-stuck-leave-approvals', ['--dry-run' => true])
        ->expectsOutputToContain('Stuck leave requests found: 1')
        ->assertExitCode(0);

    expect($leaveRequest->fresh()->status)->toBe(LeaveRequest::STATUS_PENDING);
});

test('repairs a stuck leave request by finalizing it as approved', function () {
    $leaveRequest = makeStuckLeaveRequest();

    $this->artisan('hris:fix-stuck-leave-approvals')
        ->assertExitCode(0);

    $leaveRequest->refresh();

    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->approved_at)->not->toBeNull();
});

test('leaves untouched leave requests with a genuine pending step alone', function () {
    $manager = User::factory()->create();

    ApprovalWorkflow::create([
        'name' => 'Manager approves',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => (string) $manager->id],
        ],
    ]);

    $leaveType = LeaveType::factory()->create(['default_annual_quota' => 12]);
    $employee = Employee::factory()->create();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'total_days' => 3,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    app(\App\Services\Hris\ApprovalWorkflowService::class)
        ->initializeParallelWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $this->artisan('hris:fix-stuck-leave-approvals')
        ->expectsOutputToContain('Stuck leave requests found: 0')
        ->assertExitCode(0);

    expect($leaveRequest->fresh()->status)->toBe(LeaveRequest::STATUS_PENDING);
});
