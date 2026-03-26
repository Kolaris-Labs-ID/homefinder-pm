<?php

use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Services\Hris\PayrollApprovalService;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    PayrollApprovalLog::query()->forceDelete();
    PayrollWorkflowStep::query()->delete();
    PayrollWorkflowConfig::query()->delete();
    $this->service = new PayrollApprovalService();
});

function setupWorkflow(string $module, User $financeUser): PayrollWorkflowConfig
{
    $config = PayrollWorkflowConfig::create([
        'module' => $module,
        'is_active' => true,
    ]);

    PayrollWorkflowStep::create([
        'payroll_workflow_config_id' => $config->id,
        'step_type' => PayrollWorkflowStep::STEP_FINANCE,
        'assignee_type' => 'user',
        'assignee_user_id' => $financeUser->id,
        'order' => 1,
    ]);

    return $config;
}

describe('PayrollApprovalService submitForApproval', function () {
    it('transitions calculated batch to pending_finance', function () {
        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->calculated()->create();

        $this->service->submitForApproval($batch, $submitter);

        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(1)
            ->and($batch->submitted_by)->toBe($submitter->id);
    });

    it('creates approval log on submission', function () {
        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->calculated()->create();

        $this->service->submitForApproval($batch, $submitter);

        $log = PayrollApprovalLog::where('batch_id', $batch->id)->first();
        expect($log)->not->toBeNull()
            ->and($log->action)->toBe('submitted')
            ->and($log->previous_status)->toBe('calculated')
            ->and($log->new_status)->toBe('pending_finance');
    });

    it('throws when submitting draft batch', function () {
        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->draft()->create();

        expect(fn () => $this->service->submitForApproval($batch, $submitter))
            ->toThrow(\LogicException::class);
    });

    it('throws when submitting paid batch', function () {
        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->paid()->create();

        expect(fn () => $this->service->submitForApproval($batch, $submitter))
            ->toThrow(\LogicException::class);
    });

    it('throws when no workflow config exists', function () {
        $submitter = User::factory()->create();
        $batch = PayrunBatch::factory()->calculated()->create();

        expect(fn () => $this->service->submitForApproval($batch, $submitter))
            ->toThrow(\InvalidArgumentException::class);
    });
});

describe('PayrollApprovalService approveAndPay', function () {
    it('transitions pending_finance to paid', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create(['current_finance_step_order' => 1]);

        $this->service->approveAndPay($batch, $financeUser);

        $batch->refresh();
        expect($batch->status)->toBe('paid')
            ->and($batch->paid_by)->toBe($financeUser->id)
            ->and($batch->paid_at)->not->toBeNull();
    });

    it('throws when unauthorized user tries to approve', function () {
        $financeUser = User::factory()->create();
        $randomUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create(['current_finance_step_order' => 1]);

        expect(fn () => $this->service->approveAndPay($batch, $randomUser))
            ->toThrow(AuthorizationException::class);
    });

    it('throws when batch is not pending_finance', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->draft()->create();

        expect(fn () => $this->service->approveAndPay($batch, $financeUser))
            ->toThrow(\LogicException::class);
    });

    it('allows sequence of multiple finance approvers to approve and pay', function () {
        $financeUser1 = User::factory()->create();
        $financeUser2 = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser1->id,
            'order' => 1,
        ]);

        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser2->id,
            'order' => 2,
        ]);

        $batch = PayrunBatch::factory()->pendingFinance()->create(['current_finance_step_order' => 1]);

        // Attempt jump
        expect(fn () => $this->service->approveAndPay($batch, $financeUser2))
            ->toThrow(AuthorizationException::class);

        // First user approves
        $this->service->approve($batch, $financeUser1);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(2);

        // Second user approves & pays
        $this->service->approveAndPay($batch, $financeUser2);

        $batch->refresh();
        expect($batch->status)->toBe('paid')
            ->and($batch->paid_by)->toBe($financeUser2->id);
    });
});

describe('PayrollApprovalService reject', function () {
    it('transitions pending_finance to rejected', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create(['current_finance_step_order' => 1]);

        $this->service->reject($batch, $financeUser, 'Incorrect amounts');

        $batch->refresh();
        expect($batch->status)->toBe('rejected')
            ->and($batch->current_finance_step_order)->toBeNull()
            ->and($batch->rejection_reason)->toBe('Incorrect amounts');
    });

    it('throws when reason is empty', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create(['current_finance_step_order' => 1]);

        expect(fn () => $this->service->reject($batch, $financeUser, ''))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('throws when reason is whitespace only', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create(['current_finance_step_order' => 1]);

        expect(fn () => $this->service->reject($batch, $financeUser, '   '))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('throws when rejecting draft batch', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->draft()->create();

        expect(fn () => $this->service->reject($batch, $financeUser, 'reason'))
            ->toThrow(\LogicException::class);
    });

    it('throws when rejecting paid batch', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->paid()->create();

        expect(fn () => $this->service->reject($batch, $financeUser, 'reason'))
            ->toThrow(\LogicException::class);
    });
});

describe('PayrollApprovalService resubmit', function () {
    it('transitions rejected to pending_finance', function () {
        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->rejected()->create(['current_finance_step_order' => null]);

        $this->service->resubmit($batch, $submitter);

        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(1);
    });

    it('throws when resubmitting non-rejected batch', function () {
        $submitter = User::factory()->create();
        $batch = PayrunBatch::factory()->draft()->create();

        expect(fn () => $this->service->resubmit($batch, $submitter))
            ->toThrow(\LogicException::class);
    });
});

describe('PayrollApprovalService module detection', function () {
    it('identifies PayrunBatch as monthly module', function () {
        $batch = new PayrunBatch();

        expect($this->service->getModuleForBatch($batch))->toBe(PayrollWorkflowConfig::MODULE_MONTHLY);
    });

    it('identifies WeeklyAllowanceBatch as weekly module', function () {
        $batch = new WeeklyAllowanceBatch();

        expect($this->service->getModuleForBatch($batch))->toBe(PayrollWorkflowConfig::MODULE_WEEKLY);
    });
});

describe('PayrollApprovalService canUserActOnBatch', function () {
    it('returns true for assigned finance user on pending_finance batch', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create(['current_finance_step_order' => 1]);

        expect($this->service->canUserActOnBatch($batch, $financeUser))->toBeTrue();
    });

    it('returns false for unassigned user', function () {
        $financeUser = User::factory()->create();
        $randomUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create(['current_finance_step_order' => 1]);

        expect($this->service->canUserActOnBatch($batch, $randomUser))->toBeFalse();
    });

    it('returns false for draft batch', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->draft()->create();

        expect($this->service->canUserActOnBatch($batch, $financeUser))->toBeFalse();
    });
});

describe('PayrollApprovalService full workflow integration', function () {
    it('completes full approval flow: calculated → pending_finance → paid', function () {
        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->calculated()->create();

        // Step 1: Submit
        $this->service->submitForApproval($batch, $submitter);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(1);

        // Step 2: Approve and pay
        $this->service->approveAndPay($batch, $financeUser);
        $batch->refresh();
        expect($batch->status)->toBe('paid');

        // Verify audit trail
        $logs = PayrollApprovalLog::where('batch_id', $batch->id)->orderBy('id')->get();
        expect($logs)->toHaveCount(2)
            ->and($logs[0]->action)->toBe('submitted')
            ->and($logs[1]->action)->toBe('paid');
    });

    it('completes rejection and resubmission flow', function () {
        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->calculated()->create();

        // Submit
        $this->service->submitForApproval($batch, $submitter);

        // Reject
        $this->service->reject($batch, $financeUser, 'Wrong amounts');
        $batch->refresh();
        expect($batch->status)->toBe('rejected');

        // Resubmit
        $this->service->resubmit($batch, $submitter);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance');

        // Approve
        $this->service->approveAndPay($batch, $financeUser);
        $batch->refresh();
        expect($batch->status)->toBe('paid');

        $logs = PayrollApprovalLog::where('batch_id', $batch->id)->orderBy('id')->get();
        expect($logs)->toHaveCount(4);
    });

    it('completes 3-approver sequential flow end-to-end', function () {
        $financeUser1 = User::factory()->create(['name' => 'Approver 1']);
        $financeUser2 = User::factory()->create(['name' => 'Approver 2']);
        $financeUser3 = User::factory()->create(['name' => 'Approver 3']);
        $submitter = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser1->id,
            'order' => 1,
        ]);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser2->id,
            'order' => 2,
        ]);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser3->id,
            'order' => 3,
        ]);

        $batch = PayrunBatch::factory()->calculated()->create();

        $this->service->submitForApproval($batch, $submitter);
        $batch->refresh();
        expect($batch->current_finance_step_order)->toBe(1);

        // Step 1: only user1 can act, user2 and user3 cannot
        expect($this->service->canUserActOnBatch($batch, $financeUser1))->toBeTrue();
        expect($this->service->canUserActOnBatch($batch, $financeUser2))->toBeFalse();
        expect($this->service->canUserActOnBatch($batch, $financeUser3))->toBeFalse();
        expect($this->service->isFinalFinanceStep($batch))->toBeFalse();

        $this->service->approve($batch, $financeUser1);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(2);

        // Step 2: only user2 can act
        expect($this->service->canUserActOnBatch($batch, $financeUser1))->toBeFalse();
        expect($this->service->canUserActOnBatch($batch, $financeUser2))->toBeTrue();
        expect($this->service->canUserActOnBatch($batch, $financeUser3))->toBeFalse();
        expect($this->service->isFinalFinanceStep($batch))->toBeFalse();

        $this->service->approve($batch, $financeUser2);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance')
            ->and($batch->current_finance_step_order)->toBe(3);

        // Step 3 (final): only user3 can act, and it IS the final step
        expect($this->service->canUserActOnBatch($batch, $financeUser1))->toBeFalse();
        expect($this->service->canUserActOnBatch($batch, $financeUser2))->toBeFalse();
        expect($this->service->canUserActOnBatch($batch, $financeUser3))->toBeTrue();
        expect($this->service->isFinalFinanceStep($batch))->toBeTrue();

        // Intermediate approve should throw on final step
        expect(fn () => $this->service->approve($batch, $financeUser3))
            ->toThrow(\LogicException::class);

        $this->service->approveAndPay($batch, $financeUser3);
        $batch->refresh();
        expect($batch->status)->toBe('paid')
            ->and($batch->paid_by)->toBe($financeUser3->id);

        // Verify full audit trail: submitted, approved, approved, paid
        $logs = PayrollApprovalLog::where('batch_id', $batch->id)->orderBy('id')->get();
        expect($logs)->toHaveCount(4)
            ->and($logs[0]->action)->toBe('submitted')
            ->and($logs[1]->action)->toBe('approved')
            ->and($logs[2]->action)->toBe('approved')
            ->and($logs[3]->action)->toBe('paid');
    });

    it('allows intermediate approver to reject in multi-step flow', function () {
        $financeUser1 = User::factory()->create();
        $financeUser2 = User::factory()->create();
        $financeUser3 = User::factory()->create();
        $submitter = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser1->id,
            'order' => 1,
        ]);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser2->id,
            'order' => 2,
        ]);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser3->id,
            'order' => 3,
        ]);

        $batch = PayrunBatch::factory()->calculated()->create();

        $this->service->submitForApproval($batch, $submitter);
        $this->service->approve($batch, $financeUser1);
        $batch->refresh();
        expect($batch->current_finance_step_order)->toBe(2);

        // Second approver rejects
        $this->service->reject($batch, $financeUser2, 'Numbers look wrong');
        $batch->refresh();
        expect($batch->status)->toBe('rejected')
            ->and($batch->current_finance_step_order)->toBeNull();

        // Resubmit restarts from step 1
        $this->service->resubmit($batch, $submitter);
        $batch->refresh();
        expect($batch->current_finance_step_order)->toBe(1);
        expect($this->service->canUserActOnBatch($batch, $financeUser1))->toBeTrue();
    });
});

describe('PayrollWorkflowConfig getFinanceStepsOrdered', function () {
    it('returns finance steps in correct ascending order', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        // Insert in reverse order to verify sorting works
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $user3->id,
            'order' => 3,
        ]);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $user1->id,
            'order' => 1,
        ]);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $user2->id,
            'order' => 2,
        ]);

        $steps = $config->getFinanceStepsOrdered();

        expect($steps)->toHaveCount(3)
            ->and($steps[0]->assignee_user_id)->toBe($user1->id)
            ->and($steps[0]->order)->toBe(1)
            ->and($steps[1]->assignee_user_id)->toBe($user2->id)
            ->and($steps[1]->order)->toBe(2)
            ->and($steps[2]->assignee_user_id)->toBe($user3->id)
            ->and($steps[2]->order)->toBe(3);
    });
});

