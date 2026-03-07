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

function setupWorkflow(string $module, User $financeUser, ?User $cooUser = null): PayrollWorkflowConfig
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

    if ($cooUser) {
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_COO,
            'assignee_type' => 'user',
            'assignee_user_id' => $cooUser->id,
            'order' => 2,
        ]);
    }

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

        $batch = PayrunBatch::factory()->pendingFinance()->create();

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

        $batch = PayrunBatch::factory()->pendingFinance()->create();

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
});

describe('PayrollApprovalService escalateToCoo', function () {
    it('transitions pending_finance to pending_coo', function () {
        $financeUser = User::factory()->create();
        $cooUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser, $cooUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create();

        $this->service->escalateToCoo($batch, $financeUser);

        $batch->refresh();
        expect($batch->status)->toBe('pending_coo');
    });

    it('throws when no COO step configured', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser); // no COO

        $batch = PayrunBatch::factory()->pendingFinance()->create();

        expect(fn () => $this->service->escalateToCoo($batch, $financeUser))
            ->toThrow(\LogicException::class);
    });
});

describe('PayrollApprovalService cooApproveAndPay', function () {
    it('transitions pending_coo to paid', function () {
        $financeUser = User::factory()->create();
        $cooUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser, $cooUser);

        $batch = PayrunBatch::factory()->pendingCoo()->create();

        $this->service->cooApproveAndPay($batch, $cooUser);

        $batch->refresh();
        expect($batch->status)->toBe('paid')
            ->and($batch->paid_by)->toBe($cooUser->id);
    });

    it('throws when unauthorized user tries COO approval', function () {
        $financeUser = User::factory()->create();
        $cooUser = User::factory()->create();
        $randomUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser, $cooUser);

        $batch = PayrunBatch::factory()->pendingCoo()->create();

        expect(fn () => $this->service->cooApproveAndPay($batch, $randomUser))
            ->toThrow(AuthorizationException::class);
    });
});

describe('PayrollApprovalService reject', function () {
    it('transitions pending_finance to rejected', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create();

        $this->service->reject($batch, $financeUser, 'Incorrect amounts');

        $batch->refresh();
        expect($batch->status)->toBe('rejected')
            ->and($batch->rejection_reason)->toBe('Incorrect amounts');
    });

    it('transitions pending_coo to rejected', function () {
        $financeUser = User::factory()->create();
        $cooUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser, $cooUser);

        $batch = PayrunBatch::factory()->pendingCoo()->create();

        $this->service->reject($batch, $cooUser, 'Need review');

        $batch->refresh();
        expect($batch->status)->toBe('rejected');
    });

    it('throws when reason is empty', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create();

        expect(fn () => $this->service->reject($batch, $financeUser, ''))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('throws when reason is whitespace only', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create();

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

        $batch = PayrunBatch::factory()->rejected()->create();

        $this->service->resubmit($batch, $submitter);

        $batch->refresh();
        expect($batch->status)->toBe('pending_finance');
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

        $batch = PayrunBatch::factory()->pendingFinance()->create();

        expect($this->service->canUserActOnBatch($batch, $financeUser))->toBeTrue();
    });

    it('returns false for unassigned user', function () {
        $financeUser = User::factory()->create();
        $randomUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->pendingFinance()->create();

        expect($this->service->canUserActOnBatch($batch, $randomUser))->toBeFalse();
    });

    it('returns false for draft batch', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->draft()->create();

        expect($this->service->canUserActOnBatch($batch, $financeUser))->toBeFalse();
    });
});

describe('PayrollApprovalService isEscalationAvailable', function () {
    it('returns true when COO step exists', function () {
        $financeUser = User::factory()->create();
        $cooUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser, $cooUser);

        $batch = PayrunBatch::factory()->create();

        expect($this->service->isEscalationAvailable($batch))->toBeTrue();
    });

    it('returns false when no COO step', function () {
        $financeUser = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser);

        $batch = PayrunBatch::factory()->create();

        expect($this->service->isEscalationAvailable($batch))->toBeFalse();
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
        expect($batch->status)->toBe('pending_finance');

        // Step 2: Approve and pay
        $this->service->approveAndPay($batch, $financeUser);
        $batch->refresh();
        expect($batch->status)->toBe('paid');

        // Verify audit trail
        $logs = PayrollApprovalLog::where('batch_id', $batch->id)->orderBy('id')->get();
        expect($logs)->toHaveCount(2)
            ->and($logs[0]->action)->toBe('submitted')
            ->and($logs[1]->action)->toBe('approved');
    });

    it('completes escalation flow: calculated → pending_finance → pending_coo → paid', function () {
        $financeUser = User::factory()->create();
        $cooUser = User::factory()->create();
        $submitter = User::factory()->create();
        setupWorkflow(PayrollWorkflowConfig::MODULE_MONTHLY, $financeUser, $cooUser);

        $batch = PayrunBatch::factory()->calculated()->create();

        // Step 1: Submit
        $this->service->submitForApproval($batch, $submitter);

        // Step 2: Escalate
        $this->service->escalateToCoo($batch, $financeUser);
        $batch->refresh();
        expect($batch->status)->toBe('pending_coo');

        // Step 3: COO approves
        $this->service->cooApproveAndPay($batch, $cooUser);
        $batch->refresh();
        expect($batch->status)->toBe('paid');

        $logs = PayrollApprovalLog::where('batch_id', $batch->id)->orderBy('id')->get();
        expect($logs)->toHaveCount(3);
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
});
