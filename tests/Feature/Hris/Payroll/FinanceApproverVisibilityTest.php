<?php

/**
 * Test: Finance Approver Visibility After Payroll Submission
 *
 * Verifies that all finance users assigned in workflow config can see
 * payroll records once a batch has been submitted for approval.
 *
 * Flow: calculated → pending_finance → all assigned finance users see it
 */

use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Services\Hris\PayrollApprovalService;

beforeEach(function () {
    PayrollApprovalLog::query()->forceDelete();
    PayrollWorkflowStep::query()->delete();
    PayrollWorkflowConfig::query()->delete();
    PayrunBatch::query()->delete();
    WeeklyAllowanceBatch::query()->delete();
});

describe('Finance approver visibility after payroll submission', function () {

    it('all assigned finance approvers can see a submitted PayrunBatch', function () {
        $finance1 = User::factory()->create(['name' => 'Finance User 1']);
        $finance2 = User::factory()->create(['name' => 'Finance User 2']);
        $finance3 = User::factory()->create(['name' => 'Finance User 3']);
        $submitter = User::factory()->create(['name' => 'HR Submitter']);
        $nonApprover = User::factory()->create(['name' => 'Regular User']);

        // Set up workflow with 3 finance approvers
        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        foreach ([$finance1, $finance2, $finance3] as $order => $user) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                'assignee_type' => 'user',
                'assignee_user_id' => $user->id,
                'order' => $order + 1,
            ]);
        }

        // Create a calculated batch and submit it
        $batch = PayrunBatch::factory()->calculated()->create();
        $service = new PayrollApprovalService();
        $service->submitForApproval($batch, $submitter);

        $batch->refresh();
        expect($batch->status)->toBe('pending_finance');

        // All 3 finance users should see the batch
        expect(PayrunBatch::visibleToUser($finance1)->pluck('id')->toArray())
            ->toContain($batch->id);

        expect(PayrunBatch::visibleToUser($finance2)->pluck('id')->toArray())
            ->toContain($batch->id);

        expect(PayrunBatch::visibleToUser($finance3)->pluck('id')->toArray())
            ->toContain($batch->id);

        // Non-approver should NOT see the batch
        expect(PayrunBatch::visibleToUser($nonApprover)->pluck('id')->toArray())
            ->not->toContain($batch->id);
    });

    it('all assigned finance approvers can see a submitted WeeklyAllowanceBatch', function () {
        $finance1 = User::factory()->create(['name' => 'Finance User 1']);
        $finance2 = User::factory()->create(['name' => 'Finance User 2']);
        $submitter = User::factory()->create(['name' => 'HR Submitter']);
        $nonApprover = User::factory()->create(['name' => 'Regular User']);

        // Set up workflow with 2 finance approvers
        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_WEEKLY,
            'is_active' => true,
        ]);

        foreach ([$finance1, $finance2] as $order => $user) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                'assignee_type' => 'user',
                'assignee_user_id' => $user->id,
                'order' => $order + 1,
            ]);
        }

        // Create and submit a weekly allowance batch
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => now()->startOfWeek(),
            'week_end_date' => now()->endOfWeek(),
            'payment_date' => now()->addDays(3),
            'status' => 'calculated',
        ]);

        $service = new PayrollApprovalService();
        $service->submitForApproval($batch, $submitter);

        $batch->refresh();
        expect($batch->status)->toBe('pending_finance');

        // Both finance users should see it
        expect(WeeklyAllowanceBatch::visibleToUser($finance1)->pluck('id')->toArray())
            ->toContain($batch->id);

        expect(WeeklyAllowanceBatch::visibleToUser($finance2)->pluck('id')->toArray())
            ->toContain($batch->id);

        // Non-approver should NOT see it
        expect(WeeklyAllowanceBatch::visibleToUser($nonApprover)->pluck('id')->toArray())
            ->not->toContain($batch->id);
    });

    it('finance approvers cannot see draft or calculated batches', function () {
        $finance = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $finance->id,
            'order' => 1,
        ]);

        $draftBatch = PayrunBatch::factory()->draft()->create();
        $calculatedBatch = PayrunBatch::factory()->calculated()->create();

        $visibleIds = PayrunBatch::visibleToUser($finance)->pluck('id')->toArray();

        expect($visibleIds)->not->toContain($draftBatch->id)
            ->and($visibleIds)->not->toContain($calculatedBatch->id);
    });

    it('finance approvers can see paid batches', function () {
        $finance = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $finance->id,
            'order' => 1,
        ]);

        $paidBatch = PayrunBatch::factory()->paid()->create();

        $visibleIds = PayrunBatch::visibleToUser($finance)->pluck('id')->toArray();

        expect($visibleIds)->toContain($paidBatch->id);
    });

    it('user not in workflow cannot see any batches', function () {
        $finance = User::factory()->create();
        $outsider = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $finance->id,
            'order' => 1,
        ]);

        PayrunBatch::factory()->pendingFinance()->create();
        PayrunBatch::factory()->paid()->create();

        $visibleToOutsider = PayrunBatch::visibleToUser($outsider)->count();

        expect($visibleToOutsider)->toBe(0);
    });

    it('any assigned finance approver can approve the batch', function () {
        $finance1 = User::factory()->create();
        $finance2 = User::factory()->create();
        $finance3 = User::factory()->create();
        $submitter = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        foreach ([$finance1, $finance2, $finance3] as $order => $user) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                'assignee_type' => 'user',
                'assignee_user_id' => $user->id,
                'order' => $order + 1,
            ]);
        }

        $batch = PayrunBatch::factory()->calculated()->create();
        $service = new PayrollApprovalService();

        // Submit
        $service->submitForApproval($batch, $submitter);
        $batch->refresh();

        // Finance user 2 approves (not the first one — any should work)
        $service->approveAndPay($batch, $finance2);
        $batch->refresh();

        expect($batch->status)->toBe('paid')
            ->and($batch->paid_by)->toBe($finance2->id);
    });

    it('complete flow: submit → all see it → one approves → all see paid', function () {
        $finance1 = User::factory()->create();
        $finance2 = User::factory()->create();
        $submitter = User::factory()->create();

        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);

        foreach ([$finance1, $finance2] as $order => $user) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                'assignee_type' => 'user',
                'assignee_user_id' => $user->id,
                'order' => $order + 1,
            ]);
        }

        $batch = PayrunBatch::factory()->calculated()->create();
        $service = new PayrollApprovalService();

        // Step 1: Before submission — finance users cannot see it
        expect(PayrunBatch::visibleToUser($finance1)->count())->toBe(0);
        expect(PayrunBatch::visibleToUser($finance2)->count())->toBe(0);

        // Step 2: Submit — both finance users should see it
        $service->submitForApproval($batch, $submitter);
        expect(PayrunBatch::visibleToUser($finance1)->count())->toBe(1);
        expect(PayrunBatch::visibleToUser($finance2)->count())->toBe(1);

        // Step 3: Finance1 approves and pays
        $service->approveAndPay($batch, $finance1);

        // Step 4: Both can still see it (paid status is visible)
        expect(PayrunBatch::visibleToUser($finance1)->count())->toBe(1);
        expect(PayrunBatch::visibleToUser($finance2)->count())->toBe(1);

        // Verify it's paid
        $batch->refresh();
        expect($batch->status)->toBe('paid');
    });
});
