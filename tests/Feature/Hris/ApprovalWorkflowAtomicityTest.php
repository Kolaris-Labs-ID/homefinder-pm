<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSequentialLeaveWorkflow(User $hr, User $coo): ApprovalWorkflow
{
    return ApprovalWorkflow::create([
        'name' => 'HR then COO',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => (string) $hr->id],
            ['order' => 2, 'type' => 'user', 'value' => (string) $coo->id],
        ],
    ]);
}

test('final approval rolls back the approval step when the completion side effect throws', function () {
    $hr = User::factory()->create();
    $coo = User::factory()->create();
    makeSequentialLeaveWorkflow($hr, $coo);

    $leaveType = LeaveType::factory()->create(['default_annual_quota' => 12]);
    $employee = Employee::factory()->create();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'total_days' => 3,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $service = app(ApprovalWorkflowService::class);
    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $hrStep = $service->getCurrentStep($leaveRequest);
    $service->approve($hrStep, $hr, 'HR ok');

    // Force the completion side effect (LeaveBalanceService::consume(), called from
    // LeaveRequest::markAsApproved()) to fail on the final step.
    app()->bind(LeaveBalanceService::class, fn () => new class extends LeaveBalanceService
    {
        public function consume(LeaveRequest $leaveRequest): void
        {
            throw new \RuntimeException('Simulated failure');
        }
    });

    $cooStep = $service->getCurrentStep($leaveRequest);

    try {
        $service->approve($cooStep, $coo, 'COO ok');
        $this->fail('Expected the injected failure to propagate.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toBe('Simulated failure');
    }

    $cooStep->refresh();
    $leaveRequest->refresh();

    expect($cooStep->status)->toBe(ApprovalStep::STATUS_PENDING);
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
    expect($leaveRequest->approved_at)->toBeNull();

    // The HR step (a separate, already-committed transaction) must be unaffected.
    expect($hrStep->fresh()->status)->toBe(ApprovalStep::STATUS_APPROVED);
});

test('parallel approval rolls back the approval step when the completion side effect throws', function () {
    $manager = User::factory()->create();

    $workflow = ApprovalWorkflow::create([
        'name' => 'Any manager',
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

    $service = app(ApprovalWorkflowService::class);
    $service->initializeParallelWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    app()->bind(LeaveBalanceService::class, fn () => new class extends LeaveBalanceService
    {
        public function consume(LeaveRequest $leaveRequest): void
        {
            throw new \RuntimeException('Simulated failure');
        }
    });

    $step = $service->getCurrentStep($leaveRequest);

    try {
        $service->approveParallel($step, $manager, 'ok');
        $this->fail('Expected the injected failure to propagate.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toBe('Simulated failure');
    }

    $step->refresh();
    $leaveRequest->refresh();

    expect($step->status)->toBe(ApprovalStep::STATUS_PENDING);
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
});

test('sequential HR then COO approval reaches Approved with clean final state', function () {
    $hr = User::factory()->create();
    $coo = User::factory()->create();
    makeSequentialLeaveWorkflow($hr, $coo);

    $leaveType = LeaveType::factory()->create(['default_annual_quota' => 12]);
    $employee = Employee::factory()->create();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'total_days' => 3,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    // Use the real controller-facing entry point so current_approver_id is
    // populated exactly like a live leave submission (App\Services\Hris\LeaveWorkflowService).
    app(\App\Services\Hris\LeaveWorkflowService::class)->initializeWorkflow($leaveRequest);

    $service = app(ApprovalWorkflowService::class);

    $hrStep = $service->getCurrentStep($leaveRequest);
    expect($leaveRequest->fresh()->current_approver_id)->toBe($hr->id);
    $service->approve($hrStep, $hr, 'HR ok');

    expect($leaveRequest->fresh()->status)->toBe(LeaveRequest::STATUS_PENDING);

    $cooStep = $service->getCurrentStep($leaveRequest);
    expect($cooStep->step_order)->toBe(2);
    $service->approve($cooStep, $coo, 'COO ok');

    $leaveRequest->refresh();

    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->approved_at)->not->toBeNull();
    expect($leaveRequest->current_approver_id)->toBeNull();
    expect($service->getCurrentStep($leaveRequest))->toBeNull();
    expect($hrStep->fresh()->status)->toBe(ApprovalStep::STATUS_APPROVED);
    expect($cooStep->fresh()->status)->toBe(ApprovalStep::STATUS_APPROVED);
});

test('a quota-tracked leave type cannot be over-approved beyond remaining balance', function () {
    $manager = User::factory()->create();

    $workflow = ApprovalWorkflow::create([
        'name' => 'Manager approves',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => (string) $manager->id],
        ],
    ]);

    // Use a non-hardcoded leave type code so determineStartingBalance() actually
    // honors default_annual_quota (ANNUAL/UNPAID/SICK/PATERNITY/MATERNITY are
    // hardcoded to fixed quotas regardless of what's configured on the type).
    $leaveType = LeaveType::factory()->create(['code' => 'PTO', 'default_annual_quota' => 5]);
    $employee = Employee::factory()->create();

    // Two overlapping requests, each individually valid against the 5-day quota
    // at submission time, but jointly exceeding it (5 + 3 = 8 > 5).
    $requestA = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'total_days' => 5,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);
    $requestB = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'total_days' => 3,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $service = app(ApprovalWorkflowService::class);
    $service->initializeParallelWorkflow($requestA, ApprovalWorkflow::MODULE_LEAVE);
    $service->initializeParallelWorkflow($requestB, ApprovalWorkflow::MODULE_LEAVE);

    // Request A is approved first and consumes all 5 days.
    $service->approveParallel($service->getCurrentStep($requestA), $manager, 'ok');
    expect($requestA->fresh()->status)->toBe(LeaveRequest::STATUS_APPROVED);

    // Request B's final approval must now fail cleanly instead of corrupting the balance
    // or leaving mismatched step/request state.
    $stepB = $service->getCurrentStep($requestB);

    try {
        $service->approveParallel($stepB, $manager, 'ok');
        $this->fail('Expected approval to be rejected for insufficient balance.');
    } catch (\Throwable $e) {
        // expected
    }

    $stepB->refresh();
    $requestB->refresh();

    expect($stepB->status)->toBe(ApprovalStep::STATUS_PENDING);
    expect($requestB->status)->toBe(LeaveRequest::STATUS_PENDING);
});

test('a failed final approval is logged with full diagnostic context', function () {
    $hr = User::factory()->create();
    $coo = User::factory()->create();
    makeSequentialLeaveWorkflow($hr, $coo);

    $leaveType = LeaveType::factory()->create(['default_annual_quota' => 12]);
    $employee = Employee::factory()->create();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'total_days' => 3,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $service = app(ApprovalWorkflowService::class);
    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $hrStep = $service->getCurrentStep($leaveRequest);
    $service->approve($hrStep, $hr, 'HR ok');

    app()->bind(LeaveBalanceService::class, fn () => new class extends LeaveBalanceService
    {
        public function consume(LeaveRequest $leaveRequest): void
        {
            throw new \RuntimeException('Simulated failure for logging test');
        }
    });

    $cooStep = $service->getCurrentStep($leaveRequest);

    \Illuminate\Support\Facades\Log::spy();

    try {
        $service->approve($cooStep, $coo, 'COO ok');
    } catch (\RuntimeException $e) {
        // expected
    }

    \Illuminate\Support\Facades\Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($cooStep, $leaveRequest, $coo) {
            return str_contains($message, 'Simulated failure for logging test')
                && $context['approval_step_id'] === $cooStep->id
                && $context['approvable_id'] === $leaveRequest->id
                && $context['approvable_type'] === LeaveRequest::class
                && $context['employee_id'] === $leaveRequest->employee_id
                && $context['approver_user_id'] === $coo->id
                && $context['exception_class'] === \RuntimeException::class
                && $context['exception_message'] === 'Simulated failure for logging test'
                && ! empty($context['exception_trace']);
        });
});

test('reject() is transactional: a step already processed cannot be rejected twice', function () {
    $hr = User::factory()->create();
    $coo = User::factory()->create();
    makeSequentialLeaveWorkflow($hr, $coo);

    $leaveType = LeaveType::factory()->create();
    $employee = Employee::factory()->create();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'total_days' => 3,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $service = app(ApprovalWorkflowService::class);
    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $hrStep = $service->getCurrentStep($leaveRequest);
    $service->reject($hrStep, $hr, 'Not eligible');

    $hrStep->refresh();
    $leaveRequest->refresh();

    expect($hrStep->status)->toBe(ApprovalStep::STATUS_REJECTED);
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_REJECTED);

    // Re-fetch a stale copy (as a concurrent request would hold) and try to reject
    // again: the transaction's locked re-check must catch this, not the stale
    // in-memory status on the object passed in.
    $staleStep = ApprovalStep::find($hrStep->id);
    $staleStep->status = ApprovalStep::STATUS_PENDING; // simulate a stale in-memory read

    expect(fn () => $service->reject($staleStep, $hr, 'Second attempt'))
        ->toThrow(\Exception::class, 'This step has already been processed.');

    expect($leaveRequest->fresh()->rejection_reason)->toBe('Not eligible');
});

test('reimbursement approval via the shared engine still works after the transaction change', function () {
    $approver = User::factory()->create();

    $workflow = ApprovalWorkflow::create([
        'name' => 'Reimbursement approval',
        'for_module' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
        'is_active' => true,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => (string) $approver->id],
        ],
    ]);

    $reimbursement = Reimbursement::factory()->create([
        'status' => Reimbursement::STATUS_PENDING,
    ]);

    $service = app(ApprovalWorkflowService::class);
    $service->initializeWorkflow($reimbursement, ApprovalWorkflow::MODULE_REIMBURSEMENT);

    $step = $service->getCurrentStep($reimbursement);
    expect($step)->not->toBeNull();

    $service->approve($step, $approver, 'ok');

    expect($reimbursement->fresh()->status)->toBe('approved');
    expect($step->fresh()->status)->toBe(ApprovalStep::STATUS_APPROVED);
});
