<?php

// Feature: payroll-approval-workflow, Properties 5–16: PayrollApprovalService

use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Models\User;
use App\Services\Hris\PayrollApprovalService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    PayrollApprovalLog::query()->forceDelete();
    PayrollWorkflowStep::query()->delete();
    PayrollWorkflowConfig::query()->delete();
});

/**
 * Helper: create an active workflow config with finance step(s).
 */
function createWorkflowConfig(
    string $module,
    User $financeUser = null,
    string $financeRole = null,
): PayrollWorkflowConfig {
    $config = PayrollWorkflowConfig::create([
        'module' => $module,
        'is_active' => true,
    ]);

    PayrollWorkflowStep::create([
        'payroll_workflow_config_id' => $config->id,
        'step_type' => PayrollWorkflowStep::STEP_FINANCE,
        'assignee_type' => $financeUser ? 'user' : 'role',
        'assignee_user_id' => $financeUser?->id,
        'assignee_role' => $financeRole,
        'order' => 1,
    ]);

    return $config;
}


// ============================================================================
// Property 5: No submission without active config
// ============================================================================

test('Property 5: No submission without active config — submitForApproval throws when no config exists', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 50; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $submitter = User::factory()->create();

        // Randomly pick batch type
        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);

        if ($batchClass === PayrunBatch::class) {
            $batch = PayrunBatch::factory()->calculated()->create();
        } else {
            $batch = WeeklyAllowanceBatch::create([
                'week_start_date' => now()->startOfWeek(),
                'week_end_date' => now()->endOfWeek(),
                'payment_date' => now()->addDays(3),
                'status' => 'calculated',
            ]);
        }

        $statusBefore = $batch->status;

        expect(fn () => $service->submitForApproval($batch, $submitter))
            ->toThrow(\InvalidArgumentException::class);

        $batch->refresh();
        expect($batch->status)->toBe(
            $statusBefore,
            "Iteration {$i}: Batch status should remain '{$statusBefore}' after failed submission"
        );
    }
})->group('property-test', 'payroll-approval-workflow');

// ============================================================================
// Property 6: Submit transitions to pending_finance
// ============================================================================

test('Property 6: Submit transitions to pending_finance with metadata', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 50; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);

        if ($batchClass === PayrunBatch::class) {
            $module = PayrollWorkflowConfig::MODULE_MONTHLY;
            $batch = PayrunBatch::factory()->calculated()->create();
        } else {
            $module = PayrollWorkflowConfig::MODULE_WEEKLY;
            $batch = WeeklyAllowanceBatch::create([
                'week_start_date' => now()->startOfWeek(),
                'week_end_date' => now()->endOfWeek(),
                'payment_date' => now()->addDays(3),
                'status' => 'calculated',
            ]);
        }

        createWorkflowConfig($module, financeUser: $financeUser);

        $service->submitForApproval($batch, $submitter);
        $batch->refresh();

        expect($batch->status)->toBe('pending_finance', "Iteration {$i}: Status should be pending_finance");
        expect($batch->submitted_by)->toBe($submitter->id, "Iteration {$i}: submitted_by should match submitter");
        expect($batch->submitted_at)->not->toBeNull("Iteration {$i}: submitted_at should be set");
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 7: Approve-and-pay transitions to paid with metadata
// ============================================================================

test('Property 7: Approve-and-pay transitions to paid with metadata — finance approve', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 50; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $financeUser = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);

        if ($batchClass === PayrunBatch::class) {
            $module = PayrollWorkflowConfig::MODULE_MONTHLY;
            $batch = PayrunBatch::factory()->pendingFinance()->create();
        } else {
            $module = PayrollWorkflowConfig::MODULE_WEEKLY;
            $batch = WeeklyAllowanceBatch::create([
                'week_start_date' => now()->startOfWeek(),
                'week_end_date' => now()->endOfWeek(),
                'payment_date' => now()->addDays(3),
                'status' => 'pending_finance',
                'submitted_by' => User::factory()->create()->id,
                'submitted_at' => now(),
            ]);
        }

        createWorkflowConfig($module, financeUser: $financeUser);

        $service->approveAndPay($batch, $financeUser);
        $batch->refresh();

        expect($batch->status)->toBe('paid', "Iteration {$i}: Status should be paid");
        expect($batch->paid_by)->toBe($financeUser->id, "Iteration {$i}: paid_by should match approver");
        expect($batch->paid_at)->not->toBeNull("Iteration {$i}: paid_at should be set");
    }
})->group('property-test', 'payroll-approval-workflow');



// ============================================================================
// Property 10: Rejection requires reason and transitions to rejected
// ============================================================================

test('Property 10: Rejection with non-empty reason transitions to rejected', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 50; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $financeUser = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);

        if ($batchClass === PayrunBatch::class) {
            $module = PayrollWorkflowConfig::MODULE_MONTHLY;
            $batch = PayrunBatch::factory()->pendingFinance()->create();
        } else {
            $module = PayrollWorkflowConfig::MODULE_WEEKLY;
            $batch = WeeklyAllowanceBatch::create([
                'week_start_date' => now()->startOfWeek(),
                'week_end_date' => now()->endOfWeek(),
                'payment_date' => now()->addDays(3),
                'status' => 'pending_finance',
                'submitted_by' => User::factory()->create()->id,
                'submitted_at' => now(),
            ]);
        }

        createWorkflowConfig($module, financeUser: $financeUser);

        $reason = fake()->sentence();

        $service->reject($batch, $financeUser, $reason);
        $batch->refresh();

        expect($batch->status)->toBe('rejected', "Iteration {$i}: Status should be rejected");
        expect($batch->rejection_reason)->toBe($reason, "Iteration {$i}: Rejection reason should be stored");
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 10: Rejection with empty reason throws exception', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 50; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $financeUser = User::factory()->create();
        $module = PayrollWorkflowConfig::MODULE_MONTHLY;
        $batch = PayrunBatch::factory()->pendingFinance()->create();

        createWorkflowConfig($module, financeUser: $financeUser);

        // Generate empty or whitespace-only reasons
        $emptyReason = fake()->randomElement(['', '   ', "\t", "\n", "  \n  "]);

        expect(fn () => $service->reject($batch, $financeUser, $emptyReason))
            ->toThrow(\InvalidArgumentException::class);

        $batch->refresh();
        expect($batch->status)->toBe('pending_finance', "Iteration {$i}: Status should remain pending_finance");
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 11: Rejection preserves calculation data
// ============================================================================

test('Property 11: Rejection preserves calculation data — PayrunBatch records unchanged', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 20; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $financeUser = User::factory()->create();
        $module = PayrollWorkflowConfig::MODULE_MONTHLY;

        $batch = PayrunBatch::factory()->pendingFinance()->create();

        // Create random number of payroll records
        $recordCount = fake()->numberBetween(1, 5);
        $records = PayrollRecord::factory()->count($recordCount)->forBatch($batch)->create();

        // Snapshot records before rejection
        $snapshotBefore = $records->map(fn ($r) => $r->fresh()->toArray())->toArray();

        createWorkflowConfig($module, financeUser: $financeUser);

        $service->reject($batch, $financeUser, fake()->sentence());
        $batch->refresh();

        expect($batch->status)->toBe('rejected');

        // Verify records are unchanged
        $recordsAfter = PayrollRecord::where('payrun_batch_id', $batch->id)->get();
        expect($recordsAfter)->toHaveCount($recordCount, "Iteration {$i}: Record count should be preserved");

        foreach ($snapshotBefore as $idx => $before) {
            $after = $recordsAfter->firstWhere('id', $before['id']);
            expect($after)->not->toBeNull("Iteration {$i}: Record {$before['id']} should still exist");
            expect((string) $after->net_income)->toBe(
                (string) $before['net_income'],
                "Iteration {$i}: net_income should be preserved for record {$before['id']}"
            );
            expect((string) $after->gross_income)->toBe(
                (string) $before['gross_income'],
                "Iteration {$i}: gross_income should be preserved for record {$before['id']}"
            );
        }
    }
})->group('property-test', 'payroll-approval-workflow');

// ============================================================================
// Property 12: Resubmission transitions to pending_finance
// ============================================================================

test('Property 12: Resubmission transitions to pending_finance from rejected', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 50; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);

        if ($batchClass === PayrunBatch::class) {
            $module = PayrollWorkflowConfig::MODULE_MONTHLY;
            $batch = PayrunBatch::factory()->rejected()->create();
        } else {
            $module = PayrollWorkflowConfig::MODULE_WEEKLY;
            $batch = WeeklyAllowanceBatch::create([
                'week_start_date' => now()->startOfWeek(),
                'week_end_date' => now()->endOfWeek(),
                'payment_date' => now()->addDays(3),
                'status' => 'rejected',
                'submitted_by' => User::factory()->create()->id,
                'submitted_at' => now()->subHour(),
                'rejection_reason' => 'Needs correction',
            ]);
        }

        createWorkflowConfig($module, financeUser: $financeUser);

        $service->resubmit($batch, $submitter);
        $batch->refresh();

        expect($batch->status)->toBe('pending_finance', "Iteration {$i}: Status should be pending_finance");
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 12: Resubmission on non-rejected batch throws exception', function () {
    $service = new PayrollApprovalService();
    $nonRejectedStatuses = ['draft', 'calculated', 'pending_finance', 'paid'];

    for ($i = 0; $i < 50; $i++) {
        $status = fake()->randomElement($nonRejectedStatuses);
        $submitter = User::factory()->create();

        $batch = PayrunBatch::factory()->state([
            'status' => $status,
            'submitted_by' => $status !== 'draft' ? User::factory()->create()->id : null,
            'submitted_at' => $status !== 'draft' ? now() : null,
            'paid_by' => $status === 'paid' ? User::factory()->create()->id : null,
            'paid_at' => $status === 'paid' ? now() : null,
        ])->create();

        expect(fn () => $service->resubmit($batch, $submitter))
            ->toThrow(\LogicException::class);

        $batch->refresh();
        expect($batch->status)->toBe($status, "Iteration {$i}: Status should remain '{$status}'");
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 13: Every transition creates a complete log entry
// ============================================================================

test('Property 13: Every transition creates a complete log entry', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 20; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $financeUser = User::factory()->create();
        $hrUser = User::factory()->create();
        $module = PayrollWorkflowConfig::MODULE_MONTHLY;

        createWorkflowConfig($module, financeUser: $financeUser);

        // 1. Submit
        $batch = PayrunBatch::factory()->calculated()->create();
        $logCountBefore = PayrollApprovalLog::count();
        $service->submitForApproval($batch, $hrUser);
        $batch->refresh();

        $log = PayrollApprovalLog::latest('id')->first();
        expect(PayrollApprovalLog::count())->toBe($logCountBefore + 1, "Iteration {$i}: Submit should create 1 log");
        expect($log->action)->toBe('submitted');
        expect($log->previous_status)->toBe('calculated');
        expect($log->new_status)->toBe('pending_finance');
        expect($log->acted_by)->toBe($hrUser->id);
        expect($log->batch_id)->toBe($batch->id);
        expect($log->created_at)->not->toBeNull();

        // 2. Reject
        $logCountBefore = PayrollApprovalLog::count();
        $service->reject($batch, $financeUser, 'Incorrect amounts');
        $batch->refresh();

        $log = PayrollApprovalLog::latest('id')->first();
        expect(PayrollApprovalLog::count())->toBe($logCountBefore + 1, "Iteration {$i}: Reject should create 1 log");
        expect($log->action)->toBe('rejected');
        expect($log->previous_status)->toBe('pending_finance');
        expect($log->new_status)->toBe('rejected');
        expect($log->notes)->toBe('Incorrect amounts');

        // 3. Resubmit
        $logCountBefore = PayrollApprovalLog::count();
        $service->resubmit($batch, $hrUser);
        $batch->refresh();

        $log = PayrollApprovalLog::latest('id')->first();
        expect(PayrollApprovalLog::count())->toBe($logCountBefore + 1, "Iteration {$i}: Resubmit should create 1 log");
        expect($log->action)->toBe('resubmitted');
        expect($log->previous_status)->toBe('rejected');
        expect($log->new_status)->toBe('pending_finance');

        // 4. Approve and pay
        $logCountBefore = PayrollApprovalLog::count();
        $service->approveAndPay($batch, $financeUser, 'All good');
        $batch->refresh();

        $log = PayrollApprovalLog::latest('id')->first();
        expect(PayrollApprovalLog::count())->toBe($logCountBefore + 1, "Iteration {$i}: Approve should create 1 log");
        expect($log->action)->toBe('approved');
        expect($log->previous_status)->toBe('pending_finance');
        expect($log->new_status)->toBe('paid');
        expect($log->acted_by)->toBe($financeUser->id);
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 16: Paid batch immutability
// ============================================================================

test('Property 16: Paid batch immutability — all transitions throw on paid batch', function () {
    $service = new PayrollApprovalService();

    for ($i = 0; $i < 20; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $financeUser = User::factory()->create();
        $hrUser = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);

        if ($batchClass === PayrunBatch::class) {
            $module = PayrollWorkflowConfig::MODULE_MONTHLY;
            $batch = PayrunBatch::factory()->paid()->create();
        } else {
            $module = PayrollWorkflowConfig::MODULE_WEEKLY;
            $batch = WeeklyAllowanceBatch::create([
                'week_start_date' => now()->startOfWeek(),
                'week_end_date' => now()->endOfWeek(),
                'payment_date' => now()->addDays(3),
                'status' => 'paid',
                'submitted_by' => User::factory()->create()->id,
                'submitted_at' => now()->subDay(),
                'paid_by' => User::factory()->create()->id,
                'paid_at' => now(),
            ]);
        }

        createWorkflowConfig($module, financeUser: $financeUser);

        // All transitions should throw
        expect(fn () => $service->submitForApproval($batch, $hrUser))->toThrow(\LogicException::class);
        expect(fn () => $service->approveAndPay($batch, $financeUser))->toThrow(\LogicException::class);
        expect(fn () => $service->reject($batch, $financeUser, 'reason'))->toThrow(\LogicException::class);
        expect(fn () => $service->resubmit($batch, $hrUser))->toThrow(\LogicException::class);

        $batch->refresh();
        expect($batch->status)->toBe('paid', "Iteration {$i}: Status should remain paid");

        // Verify trait methods all return false
        expect($batch->canSubmitForApproval())->toBeFalse();
        expect($batch->canBeApprovedByFinance())->toBeFalse();
        expect($batch->canBeRejected())->toBeFalse();
        expect($batch->canBeResubmitted())->toBeFalse();
        expect($batch->isPaid())->toBeTrue();
    }
})->group('property-test', 'payroll-approval-workflow');
