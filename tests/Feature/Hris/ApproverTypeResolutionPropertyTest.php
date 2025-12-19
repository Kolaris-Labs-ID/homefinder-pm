<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Feature: filament-approvals-integration, Property 3: Approver type resolution
 * Validates: Requirements 2.3
 *
 * For any workflow step configured with a role-based approver, the system SHALL resolve
 * to a user with that role; for user-based approvers, the system SHALL use the specified user ID.
 */
test('approver type resolution correctly resolves role-based approvers to users with that role', function () {
    // Create roles
    $roles = ['hr-admin', 'head_division', 'finance-admin', 'super_admin'];
    $roleUsers = [];

    foreach ($roles as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleName);
        $roleUsers[$roleName] = $user;
    }

    $service = new ApprovalWorkflowService();
    $iterations = 100;

    for ($i = 0; $i < $iterations; $i++) {
        // Randomly select a role for this iteration
        $selectedRole = fake()->randomElement($roles);
        $expectedUser = $roleUsers[$selectedRole];

        // Create workflow with role-based step
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                [
                    'order' => 1,
                    'type' => 'role',
                    'value' => $selectedRole,
                ],
            ],
            'is_active' => true,
        ]);

        // Create a leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_DRAFT,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Get the created approval step
        $step = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->first();

        // Verify the step was created
        expect($step)->not->toBeNull();

        // Verify the approver_role is set correctly
        expect($step->approver_role)->toBe($selectedRole);

        // Verify the approver_id was resolved to a user with that role
        expect($step->approver_id)->not->toBeNull();

        // Verify the resolved user has the correct role
        $resolvedUser = User::find($step->approver_id);
        expect($resolvedUser)->not->toBeNull();
        expect($resolvedUser->hasRole($selectedRole))->toBeTrue();

        // Clean up for next iteration
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('approver type resolution correctly uses specified user ID for user-based approvers', function () {
    // Create multiple users to choose from
    $users = User::factory()->count(10)->create();

    $service = new ApprovalWorkflowService();
    $iterations = 100;

    for ($i = 0; $i < $iterations; $i++) {
        // Randomly select a user for this iteration
        $selectedUser = $users->random();

        // Create workflow with user-based step
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                [
                    'order' => 1,
                    'type' => 'user',
                    'value' => $selectedUser->id,
                ],
            ],
            'is_active' => true,
        ]);

        // Create a leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_DRAFT,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Get the created approval step
        $step = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->first();

        // Verify the step was created
        expect($step)->not->toBeNull();

        // Verify the approver_id matches the specified user ID exactly
        expect($step->approver_id)->toBe($selectedUser->id);

        // Verify approver_role is null for user-based assignment
        expect($step->approver_role)->toBeNull();

        // Clean up for next iteration
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('approver type resolution handles mixed role and user-based steps correctly', function () {
    // Create roles and users
    $roles = ['hr-admin', 'head_division'];
    $roleUsers = [];

    foreach ($roles as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleName);
        $roleUsers[$roleName] = $user;
    }

    $directUsers = User::factory()->count(5)->create();

    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random number of steps (2-4)
        $stepCount = fake()->numberBetween(2, 4);
        $steps = [];
        $expectedApprovers = [];

        for ($j = 0; $j < $stepCount; $j++) {
            $stepType = fake()->randomElement(['role', 'user']);

            if ($stepType === 'role') {
                $selectedRole = fake()->randomElement($roles);
                $steps[] = [
                    'order' => $j + 1,
                    'type' => 'role',
                    'value' => $selectedRole,
                ];
                $expectedApprovers[] = [
                    'type' => 'role',
                    'role' => $selectedRole,
                    'expected_user_has_role' => true,
                ];
            } else {
                $selectedUser = $directUsers->random();
                $steps[] = [
                    'order' => $j + 1,
                    'type' => 'user',
                    'value' => $selectedUser->id,
                ];
                $expectedApprovers[] = [
                    'type' => 'user',
                    'expected_user_id' => $selectedUser->id,
                ];
            }
        }

        // Create workflow
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => $steps,
            'is_active' => true,
        ]);

        // Create a leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_DRAFT,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Get all created approval steps
        $createdSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->orderBy('step_order')
            ->get();

        // Verify correct number of steps
        expect($createdSteps)->toHaveCount($stepCount);

        // Verify each step's approver resolution
        foreach ($createdSteps as $index => $step) {
            $expected = $expectedApprovers[$index];

            if ($expected['type'] === 'role') {
                // Role-based: verify role is stored and user has that role
                expect($step->approver_role)->toBe($expected['role']);
                expect($step->approver_id)->not->toBeNull();

                $resolvedUser = User::find($step->approver_id);
                expect($resolvedUser->hasRole($expected['role']))->toBeTrue();
            } else {
                // User-based: verify exact user ID match
                expect($step->approver_id)->toBe($expected['expected_user_id']);
                expect($step->approver_role)->toBeNull();
            }
        }

        // Clean up for next iteration
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});
