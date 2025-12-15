<?php

/**
 * Property-Based Tests for Reimbursement Status Workflow
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use App\Services\Hris\ReimbursementService;

beforeEach(function () {
    // Clean up before each test
    Reimbursement::query()->delete();
});

/**
 * **Feature: hris-phase3-payroll, Property 15: Reimbursement status workflow**
 *
 * For any reimbursement, the status SHALL transition only in valid paths:
 * pending → approved → paid, or pending → rejected, and rejection SHALL
 * store the reason.
 *
 * **Validates: Requirements 4.2, 4.4, 4.5, 4.6**
 */
test('Property 15: Reimbursement status workflow', function () {
    $reimbursementService = app(ReimbursementService::class);
    $paidBy = User::factory()->create();

    // Run 100 iterations with random reimbursement scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create a fresh reimbursement for each iteration
        $reimbursement = Reimbursement::factory()->pending()->create();

        // Property 1: Initial status must be 'pending' (Requirement 4.2)
        expect($reimbursement->status)->toBe(Reimbursement::STATUS_PENDING);
        expect($reimbursement->rejection_reason)->toBeNull();
        expect($reimbursement->paid_at)->toBeNull();
        expect($reimbursement->paid_by)->toBeNull();

        // Randomly choose between approval path or rejection path
        $shouldApprove = fake()->boolean();

        if ($shouldApprove) {
            // Path 1: pending → approved → paid

            // Transition 1: pending → approved (Requirement 4.4)
            $reimbursementService->approve($reimbursement);
            $reimbursement->refresh();

            expect($reimbursement->status)->toBe(Reimbursement::STATUS_APPROVED);
            expect($reimbursement->rejection_reason)->toBeNull();
            expect($reimbursement->paid_at)->toBeNull();
            expect($reimbursement->paid_by)->toBeNull();

            // Transition 2: approved → paid (Requirement 4.6)
            $reimbursementService->markAsPaid($reimbursement, $paidBy);
            $reimbursement->refresh();

            expect($reimbursement->status)->toBe(Reimbursement::STATUS_PAID);
            expect($reimbursement->paid_at)->not->toBeNull();
            expect($reimbursement->paid_by)->toBe($paidBy->id);
            expect($reimbursement->rejection_reason)->toBeNull();

            // Property: paid_at timestamp must be recent (within last minute)
            expect($reimbursement->paid_at->diffInSeconds(now()))->toBeLessThan(60);
        } else {
            // Path 2: pending → rejected

            // Generate a random rejection reason
            $rejectionReason = fake()->sentence();

            // Transition: pending → rejected (Requirement 4.5)
            $reimbursementService->reject($reimbursement, $rejectionReason);
            $reimbursement->refresh();

            expect($reimbursement->status)->toBe(Reimbursement::STATUS_REJECTED);
            expect($reimbursement->rejection_reason)->toBe($rejectionReason);
            expect($reimbursement->paid_at)->toBeNull();
            expect($reimbursement->paid_by)->toBeNull();
        }

        // Clean up
        $reimbursement->forceDelete();
    }

    // Clean up user
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 15 Edge Cases: Status workflow boundary conditions**
 *
 * Tests edge cases for reimbursement status workflow:
 * - Cannot approve a rejected reimbursement
 * - Cannot reject an approved reimbursement
 * - Cannot mark as paid a rejected reimbursement
 * - Cannot mark as paid a pending reimbursement (must approve first)
 * - Cannot transition backwards (e.g., paid → approved)
 * - Cannot transition from paid to any other status
 * - Rejection reason is only set during rejection
 * - Payment details are only set during payment
 *
 * **Validates: Requirements 4.2, 4.4, 4.5, 4.6**
 */
test('Property 15 Edge Cases: Status workflow boundary conditions', function () {
    $reimbursementService = app(ReimbursementService::class);
    $paidBy = User::factory()->create();

    // Edge case 1: Cannot approve a rejected reimbursement
    $reimbursement1 = Reimbursement::factory()->pending()->create();
    $reimbursementService->reject($reimbursement1, 'Invalid receipt');
    $reimbursement1->refresh();

    expect($reimbursement1->status)->toBe(Reimbursement::STATUS_REJECTED);

    expect(fn () => $reimbursementService->approve($reimbursement1))
        ->toThrow(Exception::class);

    expect($reimbursement1->status)->toBe(Reimbursement::STATUS_REJECTED);

    // Edge case 2: Cannot reject an approved reimbursement
    $reimbursement2 = Reimbursement::factory()->pending()->create();
    $reimbursementService->approve($reimbursement2);
    $reimbursement2->refresh();

    expect($reimbursement2->status)->toBe(Reimbursement::STATUS_APPROVED);

    expect(fn () => $reimbursementService->reject($reimbursement2, 'Too late'))
        ->toThrow(Exception::class);

    expect($reimbursement2->status)->toBe(Reimbursement::STATUS_APPROVED);

    // Edge case 3: Cannot mark as paid a rejected reimbursement
    $reimbursement3 = Reimbursement::factory()->pending()->create();
    $reimbursementService->reject($reimbursement3, 'Incomplete');
    $reimbursement3->refresh();

    expect($reimbursement3->status)->toBe(Reimbursement::STATUS_REJECTED);

    expect(fn () => $reimbursementService->markAsPaid($reimbursement3, $paidBy))
        ->toThrow(Exception::class);

    expect($reimbursement3->status)->toBe(Reimbursement::STATUS_REJECTED);

    // Edge case 4: Cannot mark as paid a pending reimbursement (must approve first)
    $reimbursement4 = Reimbursement::factory()->pending()->create();

    expect($reimbursement4->status)->toBe(Reimbursement::STATUS_PENDING);

    expect(fn () => $reimbursementService->markAsPaid($reimbursement4, $paidBy))
        ->toThrow(Exception::class);

    expect($reimbursement4->status)->toBe(Reimbursement::STATUS_PENDING);

    // Edge case 5: Cannot transition backwards (paid → approved)
    $reimbursement5 = Reimbursement::factory()->pending()->create();
    $reimbursementService->approve($reimbursement5);
    $reimbursement5->refresh();
    $reimbursementService->markAsPaid($reimbursement5, $paidBy);
    $reimbursement5->refresh();

    expect($reimbursement5->status)->toBe(Reimbursement::STATUS_PAID);

    // Trying to approve again should fail
    expect(fn () => $reimbursementService->approve($reimbursement5))
        ->toThrow(Exception::class);

    expect($reimbursement5->status)->toBe(Reimbursement::STATUS_PAID);

    // Edge case 6: Cannot transition from paid to any other status
    expect(fn () => $reimbursementService->reject($reimbursement5, 'Too late'))
        ->toThrow(Exception::class);

    expect($reimbursement5->status)->toBe(Reimbursement::STATUS_PAID);

    // Edge case 7: Rejection reason is only set during rejection
    $reimbursement6 = Reimbursement::factory()->pending()->create();

    // At pending, rejection_reason should be null
    expect($reimbursement6->rejection_reason)->toBeNull();

    // After rejection, rejection_reason should be set
    $rejectionReason = 'Missing receipt documentation';
    $reimbursementService->reject($reimbursement6, $rejectionReason);
    $reimbursement6->refresh();

    expect($reimbursement6->rejection_reason)->toBe($rejectionReason);

    // Edge case 8: Payment details are only set during payment
    $reimbursement7 = Reimbursement::factory()->pending()->create();

    // At pending, payment details should be null
    expect($reimbursement7->paid_at)->toBeNull();
    expect($reimbursement7->paid_by)->toBeNull();

    // After approval, payment details should still be null
    $reimbursementService->approve($reimbursement7);
    $reimbursement7->refresh();

    expect($reimbursement7->paid_at)->toBeNull();
    expect($reimbursement7->paid_by)->toBeNull();

    // After payment, payment details should be set
    $reimbursementService->markAsPaid($reimbursement7, $paidBy);
    $reimbursement7->refresh();

    expect($reimbursement7->paid_at)->not->toBeNull();
    expect($reimbursement7->paid_by)->toBe($paidBy->id);

    // Clean up
    $reimbursement1->forceDelete();
    $reimbursement2->forceDelete();
    $reimbursement3->forceDelete();
    $reimbursement4->forceDelete();
    $reimbursement5->forceDelete();
    $reimbursement6->forceDelete();
    $reimbursement7->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 15 Additional: User tracking in payment**
 *
 * Tests that user information is correctly tracked during payment:
 * - paid_by is set when reimbursement is marked as paid
 * - Different users can mark as paid
 * - User IDs are correctly persisted
 * - paid_by relationship works correctly
 *
 * **Validates: Requirements 4.6**
 */
test('Property 15 Additional: User tracking in payment', function () {
    $reimbursementService = app(ReimbursementService::class);

    // Run 50 iterations with random user combinations
    for ($i = 0; $i < 50; $i++) {
        $reimbursement = Reimbursement::factory()->pending()->create();
        $paidBy = User::factory()->create();

        // Approve the reimbursement
        $reimbursementService->approve($reimbursement);
        $reimbursement->refresh();

        // Property: paid_by should be null before payment
        expect($reimbursement->paid_by)->toBeNull();

        // Mark as paid with specific user
        $reimbursementService->markAsPaid($reimbursement, $paidBy);
        $reimbursement->refresh();

        // Property: paid_by must match the paidBy user
        expect($reimbursement->paid_by)->toBe($paidBy->id);

        // Property: paid_by relationship should work
        expect($reimbursement->paidBy->id)->toBe($paidBy->id);

        // Clean up
        $reimbursement->forceDelete();
        $paidBy->forceDelete();
    }
})->group('property-test', 'hris-payroll');
