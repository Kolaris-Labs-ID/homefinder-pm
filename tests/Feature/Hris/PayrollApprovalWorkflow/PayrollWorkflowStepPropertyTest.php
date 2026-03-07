<?php

// Feature: payroll-approval-workflow, Property 3: Step assignee type validation

/**
 * Property-Based Test: Step assignee type validation
 *
 * For any PayrollWorkflowStep, if assignee_type = 'user' then assignee_user_id
 * must be a valid user ID and assignee_role must be null; if assignee_type = 'role'
 * then assignee_role must be a non-empty string and assignee_user_id must be null.
 * The canBeActedOnBy(User) method should return true if and only if the user matches
 * the assignment (user ID match or role match).
 *
 * **Validates: Requirements 1.4**
 */

use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    PayrollWorkflowStep::query()->delete();
    PayrollWorkflowConfig::query()->delete();
});

test('Property 3: Step assignee type validation — user-assigned step returns true only for the assigned user', function () {
    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();

        $config = PayrollWorkflowConfig::create([
            'module' => fake()->randomElement([
                PayrollWorkflowConfig::MODULE_MONTHLY,
                PayrollWorkflowConfig::MODULE_WEEKLY,
            ]),
            'is_active' => true,
        ]);

        // Create the assigned user and a random other user
        $assignedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $step = PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => fake()->randomElement([PayrollWorkflowStep::STEP_FINANCE, PayrollWorkflowStep::STEP_COO]),
            'assignee_type' => 'user',
            'assignee_user_id' => $assignedUser->id,
            'assignee_role' => null,
            'order' => 1,
        ]);

        // The assigned user should be able to act on this step
        expect($step->canBeActedOnBy($assignedUser))->toBeTrue(
            "Iteration {$i}: User-assigned step should return true for the assigned user (ID: {$assignedUser->id})"
        );

        // A different user should NOT be able to act on this step
        expect($step->canBeActedOnBy($otherUser))->toBeFalse(
            "Iteration {$i}: User-assigned step should return false for a different user (ID: {$otherUser->id})"
        );

        // Verify the step's data integrity
        expect($step->assignee_type)->toBe('user');
        expect($step->assignee_user_id)->toBe($assignedUser->id);
        expect($step->assignee_role)->toBeNull();
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 3: Step assignee type validation — role-assigned step returns true only for users with that role', function () {
    $roleNames = ['finance', 'coo', 'hr-admin', 'hr-staff', 'super_admin', 'manager', 'auditor'];

    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();

        $config = PayrollWorkflowConfig::create([
            'module' => fake()->randomElement([
                PayrollWorkflowConfig::MODULE_MONTHLY,
                PayrollWorkflowConfig::MODULE_WEEKLY,
            ]),
            'is_active' => true,
        ]);

        // Pick a random role name for the step
        $assignedRoleName = fake()->randomElement($roleNames);

        // Ensure the role exists in the database
        $role = Role::firstOrCreate(
            ['name' => $assignedRoleName, 'guard_name' => 'web']
        );

        // Pick a different role for the non-matching user
        $otherRoleName = fake()->randomElement(
            array_values(array_diff($roleNames, [$assignedRoleName]))
        );
        $otherRole = Role::firstOrCreate(
            ['name' => $otherRoleName, 'guard_name' => 'web']
        );

        // Create a user WITH the assigned role
        $matchingUser = User::factory()->create();
        $matchingUser->assignRole($role);

        // Create a user with a DIFFERENT role
        $nonMatchingUser = User::factory()->create();
        $nonMatchingUser->assignRole($otherRole);

        // Create a user with NO roles
        $noRoleUser = User::factory()->create();

        $step = PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => fake()->randomElement([PayrollWorkflowStep::STEP_FINANCE, PayrollWorkflowStep::STEP_COO]),
            'assignee_type' => 'role',
            'assignee_user_id' => null,
            'assignee_role' => $assignedRoleName,
            'order' => 1,
        ]);

        // User with the matching role should be able to act
        expect($step->canBeActedOnBy($matchingUser))->toBeTrue(
            "Iteration {$i}: Role-assigned step (role: {$assignedRoleName}) should return true for user with that role"
        );

        // User with a different role should NOT be able to act
        expect($step->canBeActedOnBy($nonMatchingUser))->toBeFalse(
            "Iteration {$i}: Role-assigned step (role: {$assignedRoleName}) should return false for user with role '{$otherRoleName}'"
        );

        // User with no roles should NOT be able to act
        expect($step->canBeActedOnBy($noRoleUser))->toBeFalse(
            "Iteration {$i}: Role-assigned step (role: {$assignedRoleName}) should return false for user with no roles"
        );

        // Verify the step's data integrity
        expect($step->assignee_type)->toBe('role');
        expect($step->assignee_role)->toBe($assignedRoleName);
        expect($step->assignee_user_id)->toBeNull();
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 3: Step assignee type validation — canBeActedOnBy is consistent across random assignee types', function () {
    $roleNames = ['finance', 'coo', 'hr-admin', 'hr-staff', 'super_admin'];

    for ($i = 0; $i < 100; $i++) {
        PayrollWorkflowStep::query()->delete();
        PayrollWorkflowConfig::query()->delete();

        $config = PayrollWorkflowConfig::create([
            'module' => fake()->randomElement([
                PayrollWorkflowConfig::MODULE_MONTHLY,
                PayrollWorkflowConfig::MODULE_WEEKLY,
            ]),
            'is_active' => true,
        ]);

        // Randomly choose assignee_type
        $assigneeType = fake()->randomElement(['user', 'role']);
        $user = User::factory()->create();

        if ($assigneeType === 'user') {
            $step = PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => fake()->randomElement([PayrollWorkflowStep::STEP_FINANCE, PayrollWorkflowStep::STEP_COO]),
                'assignee_type' => 'user',
                'assignee_user_id' => $user->id,
                'assignee_role' => null,
                'order' => 1,
            ]);

            // The same user should match
            expect($step->canBeActedOnBy($user))->toBeTrue(
                "Iteration {$i}: User-type step should match the assigned user"
            );

            // A different user should not match
            $differentUser = User::factory()->create();
            expect($step->canBeActedOnBy($differentUser))->toBeFalse(
                "Iteration {$i}: User-type step should not match a different user"
            );
        } else {
            $roleName = fake()->randomElement($roleNames);
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user->assignRole($role);

            $step = PayrollWorkflowStep::create([
                'payroll_workflow_config_id' => $config->id,
                'step_type' => fake()->randomElement([PayrollWorkflowStep::STEP_FINANCE, PayrollWorkflowStep::STEP_COO]),
                'assignee_type' => 'role',
                'assignee_user_id' => null,
                'assignee_role' => $roleName,
                'order' => 1,
            ]);

            // User with the role should match
            expect($step->canBeActedOnBy($user))->toBeTrue(
                "Iteration {$i}: Role-type step (role: {$roleName}) should match user with that role"
            );

            // User without the role should not match
            $userWithoutRole = User::factory()->create();
            expect($step->canBeActedOnBy($userWithoutRole))->toBeFalse(
                "Iteration {$i}: Role-type step (role: {$roleName}) should not match user without that role"
            );
        }
    }
})->group('property-test', 'payroll-approval-workflow');
