<?php

// Feature: payroll-approval-workflow, Property 15: Role-based visibility filtering

/**
 * Property-Based Test: Role-based visibility filtering
 *
 * For any set of batches with mixed statuses and any authenticated user:
 * - If the user has an HR/admin/super_admin role, all batches should be visible
 * - If the user is a finance approver, only batches with status pending_finance or paid should be visible
 * - Users with no approver role see no batches
 *
 * **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5**
 */

use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    PayrollApprovalLog::query()->forceDelete();
    PayrollWorkflowStep::query()->delete();
    PayrollWorkflowConfig::query()->delete();
});

/**
 * Helper: create an active workflow config with finance step(s).
 */
function createVisibilityWorkflowConfig(
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

/**
 * Helper: create batches with random statuses for a given model type.
 * Returns an array of created batches.
 */
function createBatchesWithRandomStatuses(string $batchClass, int $count): array
{
    $statuses = ['draft', 'calculated', 'pending_finance', 'rejected', 'paid'];
    $batches = [];

    for ($j = 0; $j < $count; $j++) {
        $status = fake()->randomElement($statuses);

        if ($batchClass === PayrunBatch::class) {
            $state = ['status' => $status];
            if (in_array($status, ['pending_finance', 'rejected', 'paid'])) {
                $state['submitted_by'] = User::factory()->create()->id;
                $state['submitted_at'] = now()->subHour();
            }
            if ($status === 'rejected') {
                $state['rejection_reason'] = 'Test rejection';
            }
            if ($status === 'paid') {
                $state['paid_by'] = User::factory()->create()->id;
                $state['paid_at'] = now();
            }
            $batches[] = PayrunBatch::factory()->state($state)->create();
        } else {
            $attrs = [
                'week_start_date' => now()->startOfWeek(),
                'week_end_date' => now()->endOfWeek(),
                'payment_date' => now()->addDays(3),
                'status' => $status,
            ];
            if (in_array($status, ['pending_finance', 'rejected', 'paid'])) {
                $attrs['submitted_by'] = User::factory()->create()->id;
                $attrs['submitted_at'] = now()->subHour();
            }
            if ($status === 'rejected') {
                $attrs['rejection_reason'] = 'Test rejection';
            }
            if ($status === 'paid') {
                $attrs['paid_by'] = User::factory()->create()->id;
                $attrs['paid_at'] = now();
            }
            $batches[] = WeeklyAllowanceBatch::create($attrs);
        }
    }

    return $batches;
}


// ============================================================================
// Property 15: Role-based visibility filtering — HR users see all batches
// ============================================================================

test('Property 15: HR users see all batches regardless of status', function () {
    for ($i = 0; $i < 30; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();
        PayrunBatch::query()->delete();
        WeeklyAllowanceBatch::query()->delete();

        $hrUser = User::factory()->create();
        Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'web']);
        $hrUser->assignRole('hr');

        $financeUser = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);
        $module = $batchClass === PayrunBatch::class
            ? PayrollWorkflowConfig::MODULE_MONTHLY
            : PayrollWorkflowConfig::MODULE_WEEKLY;

        createVisibilityWorkflowConfig($module, financeUser: $financeUser);

        $batchCount = fake()->numberBetween(2, 6);
        $batches = createBatchesWithRandomStatuses($batchClass, $batchCount);

        $visible = $batchClass::visibleToUser($hrUser)->get();

        expect($visible)->toHaveCount(
            $batchCount,
            "Iteration {$i}: HR user should see all {$batchCount} batches, got {$visible->count()}"
        );
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 15: Role-based visibility filtering — Admin users see all batches
// ============================================================================

test('Property 15: Admin users see all batches regardless of status', function () {
    for ($i = 0; $i < 30; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();
        PayrunBatch::query()->delete();
        WeeklyAllowanceBatch::query()->delete();

        $adminUser = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminUser->assignRole('admin');

        $financeUser = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);
        $module = $batchClass === PayrunBatch::class
            ? PayrollWorkflowConfig::MODULE_MONTHLY
            : PayrollWorkflowConfig::MODULE_WEEKLY;

        createVisibilityWorkflowConfig($module, financeUser: $financeUser);

        $batchCount = fake()->numberBetween(2, 6);
        $batches = createBatchesWithRandomStatuses($batchClass, $batchCount);

        $visible = $batchClass::visibleToUser($adminUser)->get();

        expect($visible)->toHaveCount(
            $batchCount,
            "Iteration {$i}: Admin user should see all {$batchCount} batches, got {$visible->count()}"
        );
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 15: Role-based visibility filtering — Super admin users see all batches
// ============================================================================

test('Property 15: Super admin users see all batches regardless of status', function () {
    for ($i = 0; $i < 30; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();
        PayrunBatch::query()->delete();
        WeeklyAllowanceBatch::query()->delete();

        $superAdminUser = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminUser->assignRole('super_admin');

        $financeUser = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);
        $module = $batchClass === PayrunBatch::class
            ? PayrollWorkflowConfig::MODULE_MONTHLY
            : PayrollWorkflowConfig::MODULE_WEEKLY;

        createVisibilityWorkflowConfig($module, financeUser: $financeUser);

        $batchCount = fake()->numberBetween(2, 6);
        $batches = createBatchesWithRandomStatuses($batchClass, $batchCount);

        $visible = $batchClass::visibleToUser($superAdminUser)->get();

        expect($visible)->toHaveCount(
            $batchCount,
            "Iteration {$i}: Super admin should see all {$batchCount} batches, got {$visible->count()}"
        );
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 15: Role-based visibility filtering — Finance approvers see only pending_finance and paid
// ============================================================================

test('Property 15: Finance approvers see only pending_finance and paid batches', function () {
    for ($i = 0; $i < 30; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();
        PayrunBatch::query()->delete();
        WeeklyAllowanceBatch::query()->delete();

        $financeUser = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);
        $module = $batchClass === PayrunBatch::class
            ? PayrollWorkflowConfig::MODULE_MONTHLY
            : PayrollWorkflowConfig::MODULE_WEEKLY;

        // Assign finance user directly to the finance step
        createVisibilityWorkflowConfig($module, financeUser: $financeUser);

        $batchCount = fake()->numberBetween(3, 8);
        $batches = createBatchesWithRandomStatuses($batchClass, $batchCount);

        $visible = $batchClass::visibleToUser($financeUser)->get();

        // Count expected visible batches (pending_finance + paid)
        $expectedCount = collect($batches)->filter(fn ($b) => in_array($b->status, ['pending_finance', 'paid']))->count();

        expect($visible)->toHaveCount(
            $expectedCount,
            "Iteration {$i}: Finance approver should see {$expectedCount} batches (pending_finance + paid), got {$visible->count()}"
        );

        // Assert all visible batches have expected statuses
        foreach ($visible as $batch) {
            expect(in_array($batch->status, ['pending_finance', 'paid']))->toBeTrue(
                "Iteration {$i}: Finance approver should not see batch with status '{$batch->status}'"
            );
        }
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 15: Role-based visibility filtering — Users with no approver role see no batches
// ============================================================================

test('Property 15: Users with no approver role see no batches', function () {
    for ($i = 0; $i < 30; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();
        PayrunBatch::query()->delete();
        WeeklyAllowanceBatch::query()->delete();

        // User with no roles at all
        $regularUser = User::factory()->create();
        $financeUser = User::factory()->create();

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);
        $module = $batchClass === PayrunBatch::class
            ? PayrollWorkflowConfig::MODULE_MONTHLY
            : PayrollWorkflowConfig::MODULE_WEEKLY;

        createVisibilityWorkflowConfig($module, financeUser: $financeUser);

        $batchCount = fake()->numberBetween(2, 6);
        createBatchesWithRandomStatuses($batchClass, $batchCount);

        $visible = $batchClass::visibleToUser($regularUser)->get();

        expect($visible)->toHaveCount(
            0,
            "Iteration {$i}: User with no approver role should see 0 batches, got {$visible->count()}"
        );
    }
})->group('property-test', 'payroll-approval-workflow');


// ============================================================================
// Property 15: Role-based visibility filtering — Finance approver via role assignment
// ============================================================================

test('Property 15: Finance approver assigned via role sees only pending_finance and paid', function () {
    for ($i = 0; $i < 30; $i++) {
        PayrollApprovalLog::query()->forceDelete();
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();
        PayrunBatch::query()->delete();
        WeeklyAllowanceBatch::query()->delete();

        $financeRoleName = 'finance_approver';
        Role::firstOrCreate(['name' => $financeRoleName, 'guard_name' => 'web']);

        $financeUser = User::factory()->create();
        $financeUser->assignRole($financeRoleName);

        $batchClass = fake()->randomElement([PayrunBatch::class, WeeklyAllowanceBatch::class]);
        $module = $batchClass === PayrunBatch::class
            ? PayrollWorkflowConfig::MODULE_MONTHLY
            : PayrollWorkflowConfig::MODULE_WEEKLY;

        // Assign finance step via role (not direct user)
        createVisibilityWorkflowConfig($module, financeRole: $financeRoleName);

        $batchCount = fake()->numberBetween(3, 8);
        $batches = createBatchesWithRandomStatuses($batchClass, $batchCount);

        $visible = $batchClass::visibleToUser($financeUser)->get();

        $expectedCount = collect($batches)->filter(fn ($b) => in_array($b->status, ['pending_finance', 'paid']))->count();

        expect($visible)->toHaveCount(
            $expectedCount,
            "Iteration {$i}: Finance approver (via role) should see {$expectedCount} batches, got {$visible->count()}"
        );

        foreach ($visible as $batch) {
            expect(in_array($batch->status, ['pending_finance', 'paid']))->toBeTrue(
                "Iteration {$i}: Finance approver (via role) should not see batch with status '{$batch->status}'"
            );
        }
    }
})->group('property-test', 'payroll-approval-workflow');
