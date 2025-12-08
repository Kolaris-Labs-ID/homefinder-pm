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
 * Feature: hris-phase2-attendance, Property 14: Approval workflow storage
 * Validates: Requirements 4.1, 4.2
 *
 * For any ApprovalWorkflow created with a name, module type, and step definitions,
 * all provided data SHALL be stored and retrievable with exact values.
 */
test('approval workflow stores all provided data accurately', function () {
    // Generate random workflow data
    $modules = [
        ApprovalWorkflow::MODULE_LEAVE,
        ApprovalWorkflow::MODULE_REIMBURSEMENT,
        ApprovalWorkflow::MODULE_PAYROLL_ADJUSTMENT,
    ];

    $iterations = 100;

    for ($i = 0; $i < $iterations; $i++) {
        $name = fake()->unique()->words(3, true);
        $module = fake()->randomElement($modules);
        $isActive = fake()->boolean();

        // Generate random step definitions
        $stepCount = fake()->numberBetween(1, 5);
        $steps = [];
        for ($j = 0; $j < $stepCount; $j++) {
            $stepType = fake()->randomElement(['role', 'user']);
            $steps[] = [
                'order' => $j + 1,
                'type' => $stepType,
                'value' => $stepType === 'role'
                    ? fake()->randomElement(['head_division', 'hr-admin', 'finance-admin'])
                    : fake()->numberBetween(1, 100),
            ];
        }

        // Create the workflow
        $workflow = ApprovalWorkflow::create([
            'name' => $name,
            'for_module' => $module,
            'steps' => $steps,
            'is_active' => $isActive,
        ]);

        // Retrieve the workflow
        $retrieved = ApprovalWorkflow::find($workflow->id);

        // Assert all data matches exactly
        expect($retrieved->name)->toBe($name)
            ->and($retrieved->for_module)->toBe($module)
            ->and($retrieved->is_active)->toBe($isActive)
            ->and($retrieved->steps)->toBe($steps);

        // Verify steps array structure
        expect($retrieved->steps)->toBeArray()
            ->and(count($retrieved->steps))->toBe($stepCount);

        foreach ($retrieved->steps as $index => $step) {
            expect($step)->toHaveKeys(['order', 'type', 'value'])
                ->and($step['order'])->toBe($steps[$index]['order'])
                ->and($step['type'])->toBe($steps[$index]['type'])
                ->and($step['value'])->toBe($steps[$index]['value']);
        }
    }
});

test('approval workflow supports role-based and user-based approver assignments', function () {
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        $steps = [];

        // Mix of role-based and user-based steps
        $stepCount = fake()->numberBetween(2, 4);
        for ($j = 0; $j < $stepCount; $j++) {
            if ($j % 2 === 0) {
                // Role-based
                $steps[] = [
                    'order' => $j + 1,
                    'type' => 'role',
                    'value' => fake()->randomElement(['head_division', 'hr-admin', 'finance-admin']),
                ];
            } else {
                // User-based
                $steps[] = [
                    'order' => $j + 1,
                    'type' => 'user',
                    'value' => fake()->numberBetween(1, 100),
                ];
            }
        }

        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => fake()->randomElement([
                ApprovalWorkflow::MODULE_LEAVE,
                ApprovalWorkflow::MODULE_REIMBURSEMENT,
            ]),
            'steps' => $steps,
            'is_active' => true,
        ]);

        $retrieved = ApprovalWorkflow::find($workflow->id);

        // Verify both types are stored correctly
        foreach ($retrieved->steps as $index => $step) {
            expect($step['type'])->toBeIn(['role', 'user']);

            if ($step['type'] === 'role') {
                expect($step['value'])->toBeString()
                    ->and($step['value'])->toBeIn(['head_division', 'hr-admin', 'finance-admin']);
            } else {
                expect($step['value'])->toBeInt()
                    ->and($step['value'])->toBeGreaterThan(0);
            }
        }
    }
});


/**
 * Feature: hris-phase2-attendance, Property 15: Workflow instantiation
 * Validates: Requirements 5.1
 *
 * For any approvable item (LeaveRequest, Reimbursement) submitted with an applicable
 * ApprovalWorkflow, the system SHALL create ApprovalStep records matching the workflow's
 * step definitions with the first step marked as pending.
 */
test('workflow instantiation creates approval steps with first step pending', function () {
    // Create roles for role-based approvers
    $hrAdminRole = Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $headDivisionRole = Role::firstOrCreate(['name' => 'head_division', 'guard_name' => 'web']);

    // Create users with roles
    $hrAdmin = User::factory()->create();
    $hrAdmin->assignRole('hr-admin');

    $headDivision = User::factory()->create();
    $headDivision->assignRole('head_division');

    $directUser = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random workflow steps
        $stepCount = fake()->numberBetween(1, 4);
        $steps = [];

        for ($j = 0; $j < $stepCount; $j++) {
            $stepType = fake()->randomElement(['role', 'user']);
            $steps[] = [
                'order' => $j + 1,
                'type' => $stepType,
                'value' => $stepType === 'role'
                    ? fake()->randomElement(['head_division', 'hr-admin'])
                    : $directUser->id,
            ];
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

        // Verify approval steps were created
        $createdSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->orderBy('step_order')
            ->get();

        // Should have same number of steps as workflow definition
        expect($createdSteps)->toHaveCount($stepCount);

        // Verify first step is pending, others are skipped
        foreach ($createdSteps as $index => $step) {
            expect($step->workflow_id)->toBe($workflow->id)
                ->and($step->step_order)->toBe($steps[$index]['order']);

            if ($index === 0) {
                expect($step->status)->toBe(ApprovalStep::STATUS_PENDING);
            } else {
                expect($step->status)->toBe(ApprovalStep::STATUS_SKIPPED);
            }

            // Verify approver was resolved
            if ($steps[$index]['type'] === 'user') {
                expect($step->approver_id)->toBe($steps[$index]['value']);
            } elseif ($steps[$index]['type'] === 'role') {
                expect($step->approver_role)->toBe($steps[$index]['value']);
                // Approver should be resolved to a user with that role
                expect($step->approver_id)->not->toBeNull();
            }
        }

        // Clean up for next iteration
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('workflow instantiation does nothing when no active workflow exists', function () {
    $service = new ApprovalWorkflowService();

    // Create a leave request without any workflow
    $leaveRequest = LeaveRequest::factory()->create([
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    // Initialize workflow (should do nothing)
    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    // Verify no approval steps were created
    $createdSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
        ->where('approvable_id', $leaveRequest->id)
        ->get();

    expect($createdSteps)->toHaveCount(0);
});

test('workflow instantiation uses only active workflows', function () {
    // Create roles
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $hrAdmin = User::factory()->create();
    $hrAdmin->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();

    // Create inactive workflow
    ApprovalWorkflow::create([
        'name' => 'Inactive Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'role', 'value' => 'hr-admin'],
        ],
        'is_active' => false,
    ]);

    // Create active workflow
    $activeWorkflow = ApprovalWorkflow::create([
        'name' => 'Active Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'role', 'value' => 'hr-admin'],
            ['order' => 2, 'type' => 'role', 'value' => 'hr-admin'],
        ],
        'is_active' => true,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'status' => LeaveRequest::STATUS_DRAFT,
    ]);

    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $createdSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
        ->where('approvable_id', $leaveRequest->id)
        ->get();

    // Should use active workflow with 2 steps
    expect($createdSteps)->toHaveCount(2)
        ->and($createdSteps->first()->workflow_id)->toBe($activeWorkflow->id);
});


/**
 * Feature: hris-phase2-attendance, Property 16: Approval progression
 * Validates: Requirements 5.2, 5.3
 *
 * For any ApprovalStep that is approved, if there are remaining steps, the next step
 * SHALL become pending. If it is the final step, the approvable item SHALL be marked as approved.
 */
test('approval progression advances to next step or marks approvable as approved', function () {
    // Create roles
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'head_division', 'guard_name' => 'web']);

    // Create approvers
    $approver1 = User::factory()->create();
    $approver1->assignRole('head_division');

    $approver2 = User::factory()->create();
    $approver2->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random number of steps (2-4)
        $stepCount = fake()->numberBetween(2, 4);
        $steps = [];

        for ($j = 0; $j < $stepCount; $j++) {
            $steps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $j % 2 === 0 ? $approver1->id : $approver2->id,
            ];
        }

        // Create workflow
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => $steps,
            'is_active' => true,
        ]);

        // Create leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Approve each step and verify progression
        for ($stepIndex = 0; $stepIndex < $stepCount; $stepIndex++) {
            $currentStep = $service->getCurrentStep($leaveRequest);

            expect($currentStep)->not->toBeNull()
                ->and($currentStep->status)->toBe(ApprovalStep::STATUS_PENDING)
                ->and($currentStep->step_order)->toBe($stepIndex + 1);

            $approver = $stepIndex % 2 === 0 ? $approver1 : $approver2;
            $notes = fake()->optional()->sentence();

            // Approve the step
            $service->approve($currentStep, $approver, $notes);

            // Refresh the step
            $currentStep->refresh();

            // Verify step is now approved
            expect($currentStep->status)->toBe(ApprovalStep::STATUS_APPROVED)
                ->and($currentStep->approved_at)->not->toBeNull()
                ->and($currentStep->approver_id)->toBe($approver->id);

            if ($notes) {
                expect($currentStep->notes)->toBe($notes);
            }

            // Check if there's a next step
            if ($stepIndex < $stepCount - 1) {
                // Should have advanced to next step
                $nextStep = $service->getCurrentStep($leaveRequest);
                expect($nextStep)->not->toBeNull()
                    ->and($nextStep->status)->toBe(ApprovalStep::STATUS_PENDING)
                    ->and($nextStep->step_order)->toBe($stepIndex + 2);
            } else {
                // Final step - approvable should be approved
                $leaveRequest->refresh();
                expect($leaveRequest->status)->toBe('approved')
                    ->and($leaveRequest->approved_at)->not->toBeNull();
            }
        }

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('approving already processed step throws exception', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();

    // Create workflow
    $workflow = ApprovalWorkflow::create([
        'name' => 'Test Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $step = $service->getCurrentStep($leaveRequest);

    // Approve once
    $service->approve($step, $approver);

    // Try to approve again - should throw exception
    expect(fn() => $service->approve($step, $approver))
        ->toThrow(\Exception::class, 'This step has already been processed.');
});

test('notification is sent to next approver after approval', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    $approver1 = User::factory()->create();
    $approver2 = User::factory()->create();

    $service = new ApprovalWorkflowService();

    // Create workflow with 2 steps
    $workflow = ApprovalWorkflow::create([
        'name' => 'Test Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver1->id],
            ['order' => 2, 'type' => 'user', 'value' => $approver2->id],
        ],
        'is_active' => true,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    // Clear any existing notifications
    \App\Models\Notification::where('user_id', $approver2->id)->delete();

    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $step = $service->getCurrentStep($leaveRequest);

    // Approve first step
    $service->approve($step, $approver1);

    // Verify notification was sent to second approver
    $notification = \App\Models\Notification::where('user_id', $approver2->id)
        ->where('type', 'approval_pending')
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toBe('Approval Required');
});


/**
 * Feature: hris-phase2-attendance, Property 17: Rejection terminates workflow
 * Validates: Requirements 5.4
 *
 * For any ApprovalStep that is rejected, the approvable item SHALL be marked as rejected
 * and no further steps SHALL be processed.
 */
test('rejection terminates workflow and marks approvable as rejected', function () {
    // Create roles
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'head_division', 'guard_name' => 'web']);

    // Create approvers
    $approver1 = User::factory()->create();
    $approver1->assignRole('head_division');

    $approver2 = User::factory()->create();
    $approver2->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random number of steps (2-4)
        $stepCount = fake()->numberBetween(2, 4);
        $steps = [];

        for ($j = 0; $j < $stepCount; $j++) {
            $steps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $j % 2 === 0 ? $approver1->id : $approver2->id,
            ];
        }

        // Create workflow
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => $steps,
            'is_active' => true,
        ]);

        // Create leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Randomly decide which step to reject (could be first or any subsequent)
        $rejectAtStep = fake()->numberBetween(1, $stepCount);

        // Approve steps until we reach the rejection point
        for ($stepIndex = 1; $stepIndex < $rejectAtStep; $stepIndex++) {
            $currentStep = $service->getCurrentStep($leaveRequest);
            $approver = ($stepIndex - 1) % 2 === 0 ? $approver1 : $approver2;
            $service->approve($currentStep, $approver);
        }

        // Now reject the current step
        $stepToReject = $service->getCurrentStep($leaveRequest);
        $rejectingApprover = ($rejectAtStep - 1) % 2 === 0 ? $approver1 : $approver2;
        $rejectionReason = fake()->sentence();

        $service->reject($stepToReject, $rejectingApprover, $rejectionReason);

        // Refresh the step
        $stepToReject->refresh();

        // Verify step is rejected
        expect($stepToReject->status)->toBe(ApprovalStep::STATUS_REJECTED)
            ->and($stepToReject->approved_at)->not->toBeNull()
            ->and($stepToReject->notes)->toBe($rejectionReason)
            ->and($stepToReject->approver_id)->toBe($rejectingApprover->id);

        // Verify approvable is rejected
        $leaveRequest->refresh();
        expect($leaveRequest->status)->toBe('rejected')
            ->and($leaveRequest->rejected_at)->not->toBeNull()
            ->and($leaveRequest->rejection_reason)->toBe($rejectionReason);

        // Verify no more pending steps exist
        $pendingStep = $service->getCurrentStep($leaveRequest);
        expect($pendingStep)->toBeNull();

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('rejecting already processed step throws exception', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();

    // Create workflow
    $workflow = ApprovalWorkflow::create([
        'name' => 'Test Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $step = $service->getCurrentStep($leaveRequest);

    // Reject once
    $service->reject($step, $approver, 'First rejection');

    // Try to reject again - should throw exception
    expect(fn() => $service->reject($step, $approver, 'Second rejection'))
        ->toThrow(\Exception::class, 'This step has already been processed.');
});

test('rejection at any step terminates entire workflow', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    $approver1 = User::factory()->create();
    $approver2 = User::factory()->create();
    $approver3 = User::factory()->create();

    $service = new ApprovalWorkflowService();

    // Create workflow with 3 steps
    $workflow = ApprovalWorkflow::create([
        'name' => 'Multi-step Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver1->id],
            ['order' => 2, 'type' => 'user', 'value' => $approver2->id],
            ['order' => 3, 'type' => 'user', 'value' => $approver3->id],
        ],
        'is_active' => true,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    // Approve first step
    $step1 = $service->getCurrentStep($leaveRequest);
    $service->approve($step1, $approver1);

    // Reject at second step
    $step2 = $service->getCurrentStep($leaveRequest);
    $service->reject($step2, $approver2, 'Rejected at step 2');

    // Verify workflow is terminated
    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('rejected');

    // Verify step 3 was never processed (still skipped)
    $allSteps = ApprovalStep::where('approvable_id', $leaveRequest->id)
        ->orderBy('step_order')
        ->get();

    expect($allSteps[0]->status)->toBe(ApprovalStep::STATUS_APPROVED)
        ->and($allSteps[1]->status)->toBe(ApprovalStep::STATUS_REJECTED)
        ->and($allSteps[2]->status)->toBe(ApprovalStep::STATUS_SKIPPED);
});
