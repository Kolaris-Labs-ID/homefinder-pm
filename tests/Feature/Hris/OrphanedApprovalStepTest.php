<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeWorkflow(): ApprovalWorkflow
{
    return ApprovalWorkflow::create([
        'name' => 'Test Leave Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [['order' => 1, 'type' => 'role', 'value' => 'hr-admin', 'label' => 'HR']],
    ]);
}

function makeLeaveRequest(): LeaveRequest
{
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => LeaveType::factory()->create()->id,
    ]);
}

function makeStep(int $workflowId, int $approvableId): ApprovalStep
{
    return ApprovalStep::create([
        'workflow_id' => $workflowId,
        'approvable_type' => LeaveRequest::class,
        'approvable_id' => $approvableId,
        'step_order' => 1,
        'status' => ApprovalStep::STATUS_PENDING,
    ]);
}

test('deleting a leave request cascades to its approval steps', function () {
    $workflow = makeWorkflow();
    $leave = makeLeaveRequest();
    makeStep($workflow->id, $leave->id);

    $leave->delete();

    expect(ApprovalStep::where('approvable_type', LeaveRequest::class)
        ->where('approvable_id', $leave->id)
        ->count())->toBe(0);
});

test('prune command deletes orphaned steps but keeps healthy ones', function () {
    $workflow = makeWorkflow();
    makeStep($workflow->id, 999999);           // orphan: no such leave request
    $healthy = makeStep($workflow->id, makeLeaveRequest()->id);

    $this->artisan('hris:prune-orphan-approval-steps', ['--delete' => true])
        ->assertExitCode(0);

    expect(ApprovalStep::where('approvable_id', 999999)->exists())->toBeFalse();
    expect(ApprovalStep::whereKey($healthy->id)->exists())->toBeTrue();
});

test('prune command dry-run reports without deleting', function () {
    $workflow = makeWorkflow();
    makeStep($workflow->id, 999999);

    $this->artisan('hris:prune-orphan-approval-steps')->assertExitCode(0);

    expect(ApprovalStep::where('approvable_id', 999999)->exists())->toBeTrue();
});

test('dashboard table query excludes orphaned steps', function () {
    $workflow = makeWorkflow();
    $orphan = makeStep($workflow->id, 999999);
    $healthy = makeStep($workflow->id, makeLeaveRequest()->id);

    $admin = User::factory()->create();
    \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
    $admin->assignRole('super_admin');

    \Livewire\Livewire::actingAs($admin)
        ->test(\App\Filament\Pages\ApprovalDashboard::class)
        ->assertCanSeeTableRecords([$healthy])
        ->assertCanNotSeeTableRecords([$orphan]);
});
