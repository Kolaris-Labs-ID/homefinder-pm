<?php

/**
 * Test: Weekly Allowance Batch Approval Workflow
 *
 * Verifies the complete weekly allowance approval flow:
 * draft → calculated (auto) → pending_finance → paid
 * Including rejection and resubmission paths.
 */

use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Services\Hris\PayrollApprovalService;

beforeEach(function () {
    PayrollApprovalLog::query()->forceDelete();
    PayrollWorkflowStep::query()->delete();
    PayrollWorkflowConfig::query()->delete();
    WeeklyAllowanceBatch::query()->delete();
    $this->service = new PayrollApprovalService();
});

function createWeeklyWorkflow(array $financeUsers): PayrollWorkflowConfig
{
    $config = PayrollWorkflowConfig::create([
        'module' => PayrollWorkflowConfig::MODULE_WEEKLY,
        'is_active' => true,
    ]);

    foreach ($financeUsers as $order => $user) {
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $user->id,
            'order' => $order + 1,
        ]);
    }

    return $config;
}

function createWeeklyBatch(string $status = 'draft', array $extra = []): WeeklyAllowanceBatch
{
    return WeeklyAllowanceBatch::create(array_merge([
        'week_start_date' => now()->startOfWeek(),
        'week_end_date' => now()->endOfWeek(),
        'payment_date' => now()->addDays(3),
        'status' => $status,
    ], $extra));
}

describe('Weekly Allowance Batch status transitions', function () {

    it('batch is created as draft', function () {
        $batch = createWeeklyBatch();

        expect($batch->status)->toBe('draft')
            ->and($batch->canSubmitForApproval())->toBeFalse();
    });

    it('calculated batch can be submitted for approval', function () {
        $batch = createWeeklyBatch('calculated');

        expect($batch->canSubmitForApproval())->toBeTrue();
    });

    it('draft batch cannot be submitted for approval', function () {
        $batch = createWeeklyBatch('draft');

        expect($batch->canSubmitForApproval())->toBeFalse();
    });

    it('pending_finance batch can be approved', function () {
        $batch = createWeeklyBatch('pending_finance', [
            'submitted_by' => User::factory()->create()->id,
            'submitted_at' => now(),
            'current_finance_step_order' => 1,
        ]);

        expect($batch->canBeApprovedByFinance())->toBeTrue();
    });

    it('pending_finance batch can be rejected', function () {
        $batch = createWeeklyBatch('pending_finance', [
            'submitted_by' => User::factory()->create()->id,
            'submitted_at' => now(),
            'current_finance_step_order' => 1,
        ]);

        expect($batch->canBeRejected())->toBeTrue();
    });

    it('rejected batch can be resubmitted', function () {
        $batch = createWeeklyBatch('rejected', [
            'submitted_by' => User::factory()->create()->id,
            'submitted_at' => now(),
            'rejection_reason' => 'Wrong amounts',
            'current_finance_step_order' => null,
        ]);

        expect($batch->canBeResubmitted())->toBeTrue();
    });

    it('paid batch cannot be modified', function () {
        $batch = createWeeklyBatch('paid', [
            'submitted_by' => User::factory()->create()->id,
            'submitted_at' => now(),
            'paid_by' => User::factory()->create()->id,
            'paid_at' => now(),
        ]);

        expect($batch->canSubmitForApproval())->toBeFalse()
            ->and($batch->canBeApprovedByFinance())->toBeFalse()
            ->and($batch->canBeRejected())->toBeFalse()
            ->and($batch->canBeResubmitted())->toBeFalse()
            ->and($batch->isPaid())->toBeTrue();
    });
});

describe('Weekly Allowance Batch approval workflow via PayrollApprovalService', function () {

    it('submits calculated batch to pending_finance', function () {
        $finance = User::factory()->create();
        $submitter = User::factory()->create();
        createWeeklyWorkflow([$finance]);

        $batch = createWeeklyBatch('calculated');

        $this->service->submitForApproval($batch, $submitter);
        $batch->refresh();

        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(1)
            ->and($batch->submitted_by)->toBe($submitter->id)
            ->and($batch->submitted_at)->not->toBeNull();
    });

    it('finance approver can approve and pay (single approver)', function () {
        $finance = User::factory()->create();
        createWeeklyWorkflow([$finance]);

        $batch = createWeeklyBatch('pending_finance', [
            'submitted_by' => User::factory()->create()->id,
            'submitted_at' => now(),
            'current_finance_step_order' => 1,
        ]);

        $this->service->approveAndPay($batch, $finance);
        $batch->refresh();

        expect($batch->status)->toBe('paid')
            ->and($batch->paid_by)->toBe($finance->id)
            ->and($batch->paid_at)->not->toBeNull();
    });

    it('finance approver can reject with reason', function () {
        $finance = User::factory()->create();
        createWeeklyWorkflow([$finance]);

        $batch = createWeeklyBatch('pending_finance', [
            'submitted_by' => User::factory()->create()->id,
            'submitted_at' => now(),
            'current_finance_step_order' => 1,
        ]);

        $this->service->reject($batch, $finance, 'Numbers are incorrect');
        $batch->refresh();

        expect($batch->status)->toBe('rejected')
            ->and($batch->current_finance_step_order)->toBeNull()
            ->and($batch->rejection_reason)->toBe('Numbers are incorrect');
    });

    it('rejected batch can be resubmitted', function () {
        $finance = User::factory()->create();
        $submitter = User::factory()->create();
        createWeeklyWorkflow([$finance]);

        $batch = createWeeklyBatch('rejected', [
            'submitted_by' => $submitter->id,
            'submitted_at' => now()->subHour(),
            'rejection_reason' => 'Wrong amounts',
            'current_finance_step_order' => null,
        ]);

        $this->service->resubmit($batch, $submitter);
        $batch->refresh();

        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(1);
    });

    it('multiple finance approvers must approve sequentially', function () {
        $finance1 = User::factory()->create();
        $finance2 = User::factory()->create();
        $finance3 = User::factory()->create();
        createWeeklyWorkflow([$finance1, $finance2, $finance3]);

        $batch = createWeeklyBatch('pending_finance', [
            'submitted_by' => User::factory()->create()->id,
            'submitted_at' => now(),
            'current_finance_step_order' => 1,
        ]);

        // Attempting to jump sequence should throw
        expect(fn () => $this->service->approveAndPay($batch, $finance3))
            ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

        // Finance 1 Approves
        $this->service->approve($batch, $finance1);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(2);

        // Finance 2 Approves
        $this->service->approve($batch, $finance2);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(3);

        // Finance 3 Approves & Pays
        $this->service->approveAndPay($batch, $finance3);
        $batch->refresh();

        expect($batch->status)->toBe('paid')
            ->and($batch->paid_by)->toBe($finance3->id);
    });

    it('unauthorized user cannot approve', function () {
        $finance = User::factory()->create();
        $outsider = User::factory()->create();
        createWeeklyWorkflow([$finance]);

        $batch = createWeeklyBatch('pending_finance', [
            'submitted_by' => User::factory()->create()->id,
            'submitted_at' => now(),
            'current_finance_step_order' => 1,
        ]);

        expect(fn () => $this->service->approveAndPay($batch, $outsider))
            ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    });
});

describe('Weekly Allowance Batch full workflow integration', function () {

    it('complete happy path: calculated → pending_finance → paid', function () {
        $finance1 = User::factory()->create();
        $finance2 = User::factory()->create();
        $submitter = User::factory()->create();
        createWeeklyWorkflow([$finance1, $finance2]);

        $batch = createWeeklyBatch('calculated');

        // Step 1: Submit
        $this->service->submitForApproval($batch, $submitter);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(1);

        // Step 2: Both finance users can see it
        expect(WeeklyAllowanceBatch::visibleToUser($finance1)->count())->toBe(1);
        expect(WeeklyAllowanceBatch::visibleToUser($finance2)->count())->toBe(1);

        // Step 3: Finance1 approves (intermediate)
        $this->service->approve($batch, $finance1);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(2);

        // Step 4: Finance2 approves and pays
        $this->service->approveAndPay($batch, $finance2);
        $batch->refresh();
        expect($batch->status)->toBe('paid');

        // Step 5: Both can still see it (paid is visible)
        expect(WeeklyAllowanceBatch::visibleToUser($finance1)->count())->toBe(1);
        expect(WeeklyAllowanceBatch::visibleToUser($finance2)->count())->toBe(1);

        // Verify audit trail
        $logs = PayrollApprovalLog::where('batch_id', $batch->id)->orderBy('id')->get();
        expect($logs)->toHaveCount(3)
            ->and($logs[0]->action)->toBe('submitted')
            ->and($logs[1]->action)->toBe('approved')
            ->and($logs[2]->action)->toBe('paid');
    });

    it('rejection and resubmission path: submit → reject → resubmit → approve', function () {
        $finance1 = User::factory()->create();
        $finance2 = User::factory()->create();
        $submitter = User::factory()->create();
        createWeeklyWorkflow([$finance1, $finance2]);

        $batch = createWeeklyBatch('calculated');

        // Submit
        $this->service->submitForApproval($batch, $submitter);
        $batch->refresh();

        // Finance 1 rejects
        $this->service->reject($batch, $finance1, 'Wrong amounts');
        $batch->refresh();
        expect($batch->status)->toBe('rejected');

        // Resubmit
        $this->service->resubmit($batch, $submitter);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(1);

        // Appprove sequence
        $this->service->approve($batch, $finance1);
        $this->service->approveAndPay($batch, $finance2);
        
        $batch->refresh();
        expect($batch->status)->toBe('paid');

        // Verify full audit trail
        $logs = PayrollApprovalLog::where('batch_id', $batch->id)->orderBy('id')->get();
        expect($logs)->toHaveCount(5)
            ->and($logs[0]->action)->toBe('submitted')
            ->and($logs[1]->action)->toBe('rejected')
            ->and($logs[2]->action)->toBe('resubmitted')
            ->and($logs[3]->action)->toBe('approved')
            ->and($logs[4]->action)->toBe('paid');
    });

    it('finance approvers cannot see draft or calculated weekly batches', function () {
        $finance = User::factory()->create();
        createWeeklyWorkflow([$finance]);

        createWeeklyBatch('draft');
        createWeeklyBatch('calculated');

        expect(WeeklyAllowanceBatch::visibleToUser($finance)->count())->toBe(0);
    });
});

