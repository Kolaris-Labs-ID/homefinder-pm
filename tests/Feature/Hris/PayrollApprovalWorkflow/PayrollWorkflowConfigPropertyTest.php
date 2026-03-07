<?php

// Feature: payroll-approval-workflow, Property 1: Config uniqueness per module

/**
 * Property-Based Test: Config uniqueness per module
 *
 * For any sequence of workflow configuration create/activate operations,
 * there should be at most one active PayrollWorkflowConfig per module
 * (monthly_payroll or weekly_allowance) at any time. Attempting to create
 * a second config for the same module should throw a unique constraint violation.
 *
 * **Validates: Requirements 1.2, 1.7**
 */

use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(function () {
    PayrollWorkflowConfig::query()->delete();
});

test('Property 1: Config uniqueness per module — at most one config per module exists at any time', function () {
    $modules = [
        PayrollWorkflowConfig::MODULE_MONTHLY,
        PayrollWorkflowConfig::MODULE_WEEKLY,
    ];

    for ($i = 0; $i < 100; $i++) {
        // Clean slate each iteration
        PayrollWorkflowConfig::query()->delete();

        // Generate a random sequence of create operations (1 to 10 operations)
        $operationCount = fake()->numberBetween(1, 10);

        for ($op = 0; $op < $operationCount; $op++) {
            $module = fake()->randomElement($modules);
            $isActive = fake()->boolean(80); // 80% chance of being active

            $existingConfig = PayrollWorkflowConfig::where('module', $module)->first();

            if ($existingConfig) {
                // A config already exists for this module — attempting to create
                // another should throw a unique constraint violation
                $threw = false;
                try {
                    PayrollWorkflowConfig::create([
                        'module' => $module,
                        'is_active' => $isActive,
                    ]);
                } catch (QueryException $e) {
                    $threw = true;
                }

                expect($threw)->toBeTrue(
                    "Iteration {$i}, op {$op}: Creating a second config for module '{$module}' "
                    . "should throw a unique constraint violation"
                );
            } else {
                // No config exists for this module — creation should succeed
                $config = PayrollWorkflowConfig::create([
                    'module' => $module,
                    'is_active' => $isActive,
                ]);

                expect($config->exists)->toBeTrue(
                    "Iteration {$i}, op {$op}: Config creation for module '{$module}' should succeed"
                );
            }

            // Invariant: at most one config per module at any point
            foreach ($modules as $m) {
                $count = PayrollWorkflowConfig::where('module', $m)->count();
                expect($count)->toBeLessThanOrEqual(
                    1,
                    "Iteration {$i}, op {$op}: Module '{$m}' has {$count} configs, expected at most 1"
                );
            }
        }
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 1: Config uniqueness per module — getActiveConfig returns at most one active config per module', function () {
    $modules = [
        PayrollWorkflowConfig::MODULE_MONTHLY,
        PayrollWorkflowConfig::MODULE_WEEKLY,
    ];

    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowConfig::query()->delete();

        // Randomly create configs for 0, 1, or 2 modules
        $modulesToCreate = fake()->randomElements($modules, fake()->numberBetween(0, 2));

        foreach ($modulesToCreate as $module) {
            PayrollWorkflowConfig::create([
                'module' => $module,
                'is_active' => fake()->boolean(70),
            ]);
        }

        // Invariant: getActiveConfig returns null or exactly one config per module
        foreach ($modules as $module) {
            $activeConfig = PayrollWorkflowConfig::getActiveConfig($module);
            $activeCount = PayrollWorkflowConfig::where('module', $module)
                ->where('is_active', true)
                ->count();

            expect($activeCount)->toBeLessThanOrEqual(
                1,
                "Iteration {$i}: Module '{$module}' has {$activeCount} active configs, expected at most 1"
            );

            if ($activeCount === 1) {
                expect($activeConfig)->not->toBeNull(
                    "Iteration {$i}: Module '{$module}' has 1 active config but getActiveConfig returned null"
                );
                expect($activeConfig->module)->toBe(
                    $module,
                    "Iteration {$i}: getActiveConfig returned config for wrong module"
                );
                expect($activeConfig->is_active)->toBeTrue(
                    "Iteration {$i}: getActiveConfig returned inactive config"
                );
            } else {
                expect($activeConfig)->toBeNull(
                    "Iteration {$i}: Module '{$module}' has no active config but getActiveConfig returned a result"
                );
            }
        }
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 1: Config uniqueness per module — duplicate module insert always throws QueryException', function () {
    $modules = [
        PayrollWorkflowConfig::MODULE_MONTHLY,
        PayrollWorkflowConfig::MODULE_WEEKLY,
    ];

    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowConfig::query()->delete();

        $module = fake()->randomElement($modules);

        // Create the first config — should succeed
        PayrollWorkflowConfig::create([
            'module' => $module,
            'is_active' => fake()->boolean(),
        ]);

        // Attempt to create a second config with the same module — should always fail
        $duplicateIsActive = fake()->boolean();

        expect(fn () => PayrollWorkflowConfig::create([
            'module' => $module,
            'is_active' => $duplicateIsActive,
        ]))->toThrow(
            QueryException::class,
            '',
            "Iteration {$i}: Duplicate config for module '{$module}' should throw QueryException"
        );

        // Verify only one config exists
        $count = PayrollWorkflowConfig::where('module', $module)->count();
        expect($count)->toBe(
            1,
            "Iteration {$i}: Expected exactly 1 config for module '{$module}' after failed duplicate, got {$count}"
        );
    }
})->group('property-test', 'payroll-approval-workflow');


// Feature: payroll-approval-workflow, Property 2: Config requires finance step

/**
 * Property-Based Test: Config requires finance step
 *
 * For any valid PayrollWorkflowConfig, it must contain exactly one
 * PayrollWorkflowStep with step_type = 'finance'. The COO step is optional
 * (0 or 1 steps with step_type = 'coo'). A config with zero finance steps
 * or more than one finance step should be rejected by validation.
 *
 * **Validates: Requirements 1.3**
 */

use App\Models\Hris\PayrollWorkflowStep;

test('Property 2: Config requires finance step — validation passes only with exactly 1 finance and 0–1 COO', function () {
    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $module = fake()->randomElement([
            PayrollWorkflowConfig::MODULE_MONTHLY,
            PayrollWorkflowConfig::MODULE_WEEKLY,
        ]);

        $config = PayrollWorkflowConfig::create([
            'module' => $module,
            'is_active' => true,
        ]);

        // Generate random step counts: 0–3 finance, 0–2 COO
        $financeCount = fake()->numberBetween(0, 3);
        $cooCount = fake()->numberBetween(0, 2);

        $order = 1;
        for ($f = 0; $f < $financeCount; $f++) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                'assignee_type' => 'role',
                'assignee_role' => 'finance',
                'order' => $order++,
            ]);
        }

        for ($c = 0; $c < $cooCount; $c++) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_COO,
                'assignee_type' => 'role',
                'assignee_role' => 'coo',
                'order' => $order++,
            ]);
        }

        $result = $config->validateSteps();
        $expectedValid = ($financeCount === 1 && $cooCount <= 1);

        expect($result['valid'])->toBe(
            $expectedValid,
            "Iteration {$i}: finance={$financeCount}, coo={$cooCount} — "
            . "expected valid=" . ($expectedValid ? 'true' : 'false')
            . ", got valid=" . ($result['valid'] ? 'true' : 'false')
        );

        if (!$expectedValid) {
            expect($result['errors'])->not->toBeEmpty(
                "Iteration {$i}: Invalid config should have error messages"
            );
        } else {
            expect($result['errors'])->toBeEmpty(
                "Iteration {$i}: Valid config should have no error messages"
            );
        }
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 2: Config requires finance step — zero finance steps always fails validation', function () {
    for ($i = 0; $i < 50; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $config = PayrollWorkflowConfig::create([
            'module' => fake()->randomElement([
                PayrollWorkflowConfig::MODULE_MONTHLY,
                PayrollWorkflowConfig::MODULE_WEEKLY,
            ]),
            'is_active' => true,
        ]);

        // Only COO steps, no finance
        $cooCount = fake()->numberBetween(0, 2);
        for ($c = 0; $c < $cooCount; $c++) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_COO,
                'assignee_type' => 'role',
                'assignee_role' => 'coo',
                'order' => $c + 1,
            ]);
        }

        $result = $config->validateSteps();

        expect($result['valid'])->toBeFalse(
            "Iteration {$i}: Config with 0 finance steps and {$cooCount} COO steps should be invalid"
        );
        expect($result['errors'])->toContain(
            "Config must have exactly 1 finance step, found 0."
        );
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 2: Config requires finance step — multiple finance steps always fails validation', function () {
    for ($i = 0; $i < 50; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $config = PayrollWorkflowConfig::create([
            'module' => fake()->randomElement([
                PayrollWorkflowConfig::MODULE_MONTHLY,
                PayrollWorkflowConfig::MODULE_WEEKLY,
            ]),
            'is_active' => true,
        ]);

        // 2 or 3 finance steps
        $financeCount = fake()->numberBetween(2, 3);
        $cooCount = fake()->numberBetween(0, 1);

        $order = 1;
        for ($f = 0; $f < $financeCount; $f++) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                'assignee_type' => 'role',
                'assignee_role' => 'finance',
                'order' => $order++,
            ]);
        }
        for ($c = 0; $c < $cooCount; $c++) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_COO,
                'assignee_type' => 'role',
                'assignee_role' => 'coo',
                'order' => $order++,
            ]);
        }

        $result = $config->validateSteps();

        expect($result['valid'])->toBeFalse(
            "Iteration {$i}: Config with {$financeCount} finance steps should be invalid"
        );
        expect($result['errors'])->toContain(
            "Config must have exactly 1 finance step, found {$financeCount}."
        );
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 2: Config requires finance step — exactly 1 finance and 0–1 COO always passes validation', function () {
    for ($i = 0; $i < 50; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $config = PayrollWorkflowConfig::create([
            'module' => fake()->randomElement([
                PayrollWorkflowConfig::MODULE_MONTHLY,
                PayrollWorkflowConfig::MODULE_WEEKLY,
            ]),
            'is_active' => true,
        ]);

        // Exactly 1 finance step
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'role',
            'assignee_role' => 'finance',
            'order' => 1,
        ]);

        // 0 or 1 COO steps
        $cooCount = fake()->numberBetween(0, 1);
        if ($cooCount === 1) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_COO,
                'assignee_type' => 'role',
                'assignee_role' => 'coo',
                'order' => 2,
            ]);
        }

        $result = $config->validateSteps();

        expect($result['valid'])->toBeTrue(
            "Iteration {$i}: Config with 1 finance and {$cooCount} COO should be valid"
        );
        expect($result['errors'])->toBeEmpty(
            "Iteration {$i}: Valid config should have no errors"
        );
    }
})->group('property-test', 'payroll-approval-workflow');


// Feature: payroll-approval-workflow, Property 4: Config persistence round-trip

/**
 * Property-Based Test: Config persistence round-trip
 *
 * For any valid workflow configuration (module identifier, finance step with assignee,
 * optional COO step with assignee), saving it to the database and reading it back
 * should produce an equivalent configuration with all step definitions and approver
 * assignments intact.
 *
 * **Validates: Requirements 1.5**
 */

test('Property 4: Config persistence round-trip — saved config with steps can be reloaded with all fields intact', function () {
    $modules = [
        PayrollWorkflowConfig::MODULE_MONTHLY,
        PayrollWorkflowConfig::MODULE_WEEKLY,
    ];
    $roleNames = ['finance', 'coo', 'hr-admin', 'hr-staff', 'super_admin', 'manager'];

    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        // Generate random valid config parameters
        $module = fake()->randomElement($modules);
        $isActive = fake()->boolean(80);
        $includeCoo = fake()->boolean(50);

        // Generate random finance step assignee
        $financeAssigneeType = fake()->randomElement(['user', 'role']);
        $financeUser = null;
        $financeRole = null;
        if ($financeAssigneeType === 'user') {
            $financeUser = User::factory()->create();
        } else {
            $financeRole = fake()->randomElement($roleNames);
        }

        // Generate random COO step assignee (if included)
        $cooAssigneeType = null;
        $cooUser = null;
        $cooRole = null;
        if ($includeCoo) {
            $cooAssigneeType = fake()->randomElement(['user', 'role']);
            if ($cooAssigneeType === 'user') {
                $cooUser = User::factory()->create();
            } else {
                $cooRole = fake()->randomElement($roleNames);
            }
        }

        // Save config
        $config = PayrollWorkflowConfig::create([
            'module' => $module,
            'is_active' => $isActive,
        ]);

        // Save finance step
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => $financeAssigneeType,
            'assignee_user_id' => $financeUser?->id,
            'assignee_role' => $financeRole,
            'order' => 1,
        ]);

        // Save COO step if included
        if ($includeCoo) {
            PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => PayrollWorkflowStep::STEP_COO,
                'assignee_type' => $cooAssigneeType,
                'assignee_user_id' => $cooUser?->id,
                'assignee_role' => $cooRole,
                'order' => 2,
            ]);
        }

        // Reload from database (fresh query, no cache)
        $reloaded = PayrollWorkflowConfig::with('steps')->find($config->id);

        // Assert config fields match
        expect($reloaded)->not->toBeNull(
            "Iteration {$i}: Reloaded config should not be null"
        );
        expect($reloaded->module)->toBe(
            $module,
            "Iteration {$i}: Module should be '{$module}', got '{$reloaded->module}'"
        );
        expect($reloaded->is_active)->toBe(
            $isActive,
            "Iteration {$i}: is_active should be " . ($isActive ? 'true' : 'false')
        );

        // Assert step count
        $expectedStepCount = $includeCoo ? 2 : 1;
        expect($reloaded->steps)->toHaveCount(
            $expectedStepCount,
            "Iteration {$i}: Expected {$expectedStepCount} steps, got {$reloaded->steps->count()}"
        );

        // Assert finance step fields
        $reloadedFinance = $reloaded->steps->firstWhere('step_type', PayrollWorkflowStep::STEP_FINANCE);
        expect($reloadedFinance)->not->toBeNull(
            "Iteration {$i}: Reloaded config should have a finance step"
        );
        expect($reloadedFinance->assignee_type)->toBe(
            $financeAssigneeType,
            "Iteration {$i}: Finance assignee_type should be '{$financeAssigneeType}'"
        );
        expect($reloadedFinance->assignee_user_id)->toBe(
            $financeUser?->id,
            "Iteration {$i}: Finance assignee_user_id mismatch"
        );
        expect($reloadedFinance->assignee_role)->toBe(
            $financeRole,
            "Iteration {$i}: Finance assignee_role mismatch"
        );
        expect($reloadedFinance->order)->toBe(
            1,
            "Iteration {$i}: Finance step order should be 1"
        );

        // Assert COO step fields if included
        if ($includeCoo) {
            $reloadedCoo = $reloaded->steps->firstWhere('step_type', PayrollWorkflowStep::STEP_COO);
            expect($reloadedCoo)->not->toBeNull(
                "Iteration {$i}: Reloaded config should have a COO step"
            );
            expect($reloadedCoo->assignee_type)->toBe(
                $cooAssigneeType,
                "Iteration {$i}: COO assignee_type should be '{$cooAssigneeType}'"
            );
            expect($reloadedCoo->assignee_user_id)->toBe(
                $cooUser?->id,
                "Iteration {$i}: COO assignee_user_id mismatch"
            );
            expect($reloadedCoo->assignee_role)->toBe(
                $cooRole,
                "Iteration {$i}: COO assignee_role mismatch"
            );
            expect($reloadedCoo->order)->toBe(
                2,
                "Iteration {$i}: COO step order should be 2"
            );
        } else {
            $reloadedCoo = $reloaded->steps->firstWhere('step_type', PayrollWorkflowStep::STEP_COO);
            expect($reloadedCoo)->toBeNull(
                "Iteration {$i}: Config without COO should not have a COO step after reload"
            );
        }
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 4: Config persistence round-trip — getActiveConfig returns persisted config with correct module', function () {
    $modules = [
        PayrollWorkflowConfig::MODULE_MONTHLY,
        PayrollWorkflowConfig::MODULE_WEEKLY,
    ];

    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $module = fake()->randomElement($modules);
        $isActive = fake()->boolean(70);

        $config = PayrollWorkflowConfig::create([
            'module' => $module,
            'is_active' => $isActive,
        ]);

        // Create a finance step with random assignee
        $assigneeType = fake()->randomElement(['user', 'role']);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => $assigneeType,
            'assignee_user_id' => $assigneeType === 'user' ? User::factory()->create()->id : null,
            'assignee_role' => $assigneeType === 'role' ? fake()->randomElement(['finance', 'coo', 'hr-admin']) : null,
            'order' => 1,
        ]);

        // Retrieve via getActiveConfig
        $retrieved = PayrollWorkflowConfig::getActiveConfig($module);

        if ($isActive) {
            expect($retrieved)->not->toBeNull(
                "Iteration {$i}: Active config for '{$module}' should be retrievable"
            );
            expect($retrieved->id)->toBe(
                $config->id,
                "Iteration {$i}: Retrieved config ID should match saved config ID"
            );
            expect($retrieved->module)->toBe(
                $module,
                "Iteration {$i}: Retrieved config module should match"
            );
            expect($retrieved->is_active)->toBeTrue(
                "Iteration {$i}: Retrieved config should be active"
            );
        } else {
            expect($retrieved)->toBeNull(
                "Iteration {$i}: Inactive config for '{$module}' should not be returned by getActiveConfig"
            );
        }
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 4: Config persistence round-trip — steps relationship is correctly persisted and reloaded via fresh query', function () {
    $roleNames = ['finance', 'coo', 'hr-admin', 'hr-staff', 'super_admin'];

    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowConfig::query()->delete();
        PayrollWorkflowStep::query()->delete();

        $module = fake()->randomElement([
            PayrollWorkflowConfig::MODULE_MONTHLY,
            PayrollWorkflowConfig::MODULE_WEEKLY,
        ]);

        $config = PayrollWorkflowConfig::create([
            'module' => $module,
            'is_active' => true,
        ]);

        // Build expected steps data
        $expectedSteps = [];

        // Always add finance step
        $finAssigneeType = fake()->randomElement(['user', 'role']);
        $finUserId = $finAssigneeType === 'user' ? User::factory()->create()->id : null;
        $finRole = $finAssigneeType === 'role' ? fake()->randomElement($roleNames) : null;

        $expectedSteps[] = [
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => $finAssigneeType,
            'assignee_user_id' => $finUserId,
            'assignee_role' => $finRole,
            'order' => 1,
        ];

        // Optionally add COO step
        if (fake()->boolean(50)) {
            $cooAssigneeType = fake()->randomElement(['user', 'role']);
            $cooUserId = $cooAssigneeType === 'user' ? User::factory()->create()->id : null;
            $cooRole = $cooAssigneeType === 'role' ? fake()->randomElement($roleNames) : null;

            $expectedSteps[] = [
                'step_type' => PayrollWorkflowStep::STEP_COO,
                'assignee_type' => $cooAssigneeType,
                'assignee_user_id' => $cooUserId,
                'assignee_role' => $cooRole,
                'order' => 2,
            ];
        }

        // Persist all steps
        foreach ($expectedSteps as $stepData) {
            PayrollWorkflowStep::create(array_merge(
                $stepData,
                ['payroll_workflow_config_id' => $config->id]
            ));
        }

        // Fresh reload using a completely new query (no model cache)
        $freshConfig = PayrollWorkflowConfig::query()
            ->where('id', $config->id)
            ->with('steps')
            ->first();

        expect($freshConfig->steps)->toHaveCount(
            count($expectedSteps),
            "Iteration {$i}: Step count mismatch after fresh reload"
        );

        // Verify each expected step exists with matching fields
        foreach ($expectedSteps as $expected) {
            $match = $freshConfig->steps->first(function ($step) use ($expected) {
                return $step->step_type === $expected['step_type']
                    && $step->assignee_type === $expected['assignee_type']
                    && $step->assignee_user_id === $expected['assignee_user_id']
                    && $step->assignee_role === $expected['assignee_role']
                    && $step->order === $expected['order'];
            });

            expect($match)->not->toBeNull(
                "Iteration {$i}: Expected step (type={$expected['step_type']}, "
                . "assignee_type={$expected['assignee_type']}, order={$expected['order']}) "
                . "not found after reload"
            );
        }
    }
})->group('property-test', 'payroll-approval-workflow');
