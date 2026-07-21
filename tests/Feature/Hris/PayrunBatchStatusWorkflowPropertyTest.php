<?php

/**
 * Property-Based Tests for PayrunBatch Status Workflow
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\PayrollCalculationService;

beforeEach(function () {
    // Clean up before each test
    PayrunBatch::query()->delete();
    PayrollRecord::query()->delete();
    SalaryStructure::query()->delete();
});

/**
 * **Feature: hris-phase3-payroll, Property 5: Payrun batch status workflow**
 *
 * For any payrun batch, the status SHALL transition only in the order:
 * draft → calculated → approved → paid, and each transition SHALL record
 * the appropriate timestamp.
 *
 * **Validates: Requirements 2.2, 2.4, 2.5, 2.6**
 */
test('Property 5: Payrun batch status workflow', function () {
    $payrollService = app(PayrollCalculationService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Run 100 iterations with random payrun batch scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create a fresh payrun batch for each iteration
        $batch = PayrunBatch::factory()->draft()->create();

        // Property 1: Initial status must be 'draft' (Requirement 2.2)
        expect($batch->status)->toBe(PayrunBatch::STATUS_DRAFT);
        expect($batch->calculated_at)->toBeNull();
        expect($batch->approved_at)->toBeNull();
        expect($batch->paid_at)->toBeNull();

        // Create some employees and salary structures for calculation
        $employees = Employee::factory()->count(fake()->numberBetween(1, 5))->create();
        foreach ($employees as $employee) {
            SalaryStructure::factory()->create([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_start_date,
            ]);
        }

        // Transition 1: draft → calculated (Requirement 2.4)
        $payrollService->calculatePayrun($batch);
        $batch->refresh();

        expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
        expect($batch->calculated_at)->not->toBeNull();
        expect($batch->approved_at)->toBeNull();
        expect($batch->paid_at)->toBeNull();

        // Property: calculated_at timestamp must be recent (within last minute)
        expect($batch->calculated_at->diffInSeconds(now()))->toBeLessThan(60);

        // Property: Payroll records must be created (Requirement 2.3)
        $recordCount = $batch->payrollRecords()->count();
        expect($recordCount)->toBeGreaterThan(0);

        // Transition 2: calculated → approved (Requirement 2.5)
        $payrollService->approve($batch, $approver);
        $batch->refresh();

        expect($batch->status)->toBe(PayrunBatch::STATUS_PENDING_FINANCE);
        expect($batch->approved_at)->not->toBeNull();
        expect($batch->approved_by)->toBe($approver->id);
        expect($batch->paid_at)->toBeNull();

        // Property: approved_at timestamp must be recent
        expect($batch->approved_at->diffInSeconds(now()))->toBeLessThan(60);

        // Property: approved_at must be >= calculated_at
        expect($batch->approved_at->getTimestamp())->toBeGreaterThanOrEqual(
            $batch->calculated_at->getTimestamp()
        );

        // Transition 3: approved → paid (Requirement 2.6)
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        expect($batch->status)->toBe(PayrunBatch::STATUS_PAID);
        expect($batch->paid_at)->not->toBeNull();
        expect($batch->paid_by)->toBe($paidBy->id);

        // Property: paid_at timestamp must be recent
        expect($batch->paid_at->diffInSeconds(now()))->toBeLessThan(60);

        // Property: paid_at must be >= approved_at
        expect($batch->paid_at->getTimestamp())->toBeGreaterThanOrEqual(
            $batch->approved_at->getTimestamp()
        );

        // Property: Timestamp ordering must be maintained
        // calculated_at <= approved_at <= paid_at
        expect($batch->calculated_at->getTimestamp())->toBeLessThanOrEqual(
            $batch->approved_at->getTimestamp()
        );
        expect($batch->approved_at->getTimestamp())->toBeLessThanOrEqual(
            $batch->paid_at->getTimestamp()
        );

        // Clean up
        $batch->payrollRecords()->forceDelete();
        $batch->forceDelete();
        foreach ($employees as $employee) {
            $employee->user->forceDelete();
            $employee->forceDelete();
        }
    }

    // Clean up users
    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');

/**
 * **Feature: hris-phase3-payroll, Property 5 Edge Cases: Status workflow boundary conditions**
 *
 * Tests edge cases for payrun batch status workflow:
 * - Cannot transition from draft directly to approved (must go through calculated)
 * - Cannot transition from draft directly to paid
 * - Cannot transition from calculated directly to paid (must go through approved)
 * - Cannot transition backwards (e.g., approved → calculated)
 * - Cannot transition from paid to any other status
 * - Timestamps are only set during their respective transitions
 *
 * **Validates: Requirements 2.2, 2.4, 2.5, 2.6**
 */
test('Property 5 Edge Cases: Status workflow boundary conditions', function () {
    $payrollService = app(PayrollCalculationService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // $batch is still alive and PAID (locked) when $batch2's salary structures are created,
    // so the two batches must occupy distinct periods or the payroll lock guard rejects them.
    // Edge case 1: Cannot approve a draft batch (must calculate first)
    $batch = PayrunBatch::factory()->draft()->previousMonth()->create();

    expect(fn () => $payrollService->approve($batch, $approver))
        ->toThrow(Exception::class);

    expect($batch->status)->toBe(PayrunBatch::STATUS_DRAFT);

    // Edge case 2: Cannot mark as paid a draft batch
    expect(fn () => $payrollService->markAsPaid($batch, $paidBy))
        ->toThrow(Exception::class);

    expect($batch->status)->toBe(PayrunBatch::STATUS_DRAFT);

    // Edge case 3: Cannot mark as paid a calculated batch (must approve first)
    // Create employees and salary structures
    $employees = Employee::factory()->count(2)->create();
    foreach ($employees as $employee) {
        SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);
    }

    $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);

    expect(fn () => $payrollService->markAsPaid($batch, $paidBy))
        ->toThrow(Exception::class);

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);

    // Edge case 4: Cannot transition backwards (approved → calculated)
    $payrollService->approve($batch, $approver);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_PENDING_FINANCE);

    // Trying to calculate again should fail
    expect(fn () => $payrollService->calculatePayrun($batch))
        ->toThrow(Exception::class);

    expect($batch->status)->toBe(PayrunBatch::STATUS_PENDING_FINANCE);

    // Edge case 5: Cannot transition backwards (paid → approved)
    $payrollService->markAsPaid($batch, $paidBy);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_PAID);

    // Trying to approve again should fail
    expect(fn () => $payrollService->approve($batch, $approver))
        ->toThrow(Exception::class);

    expect($batch->status)->toBe(PayrunBatch::STATUS_PAID);

    // Edge case 6: Cannot transition from paid to any other status
    expect(fn () => $payrollService->calculatePayrun($batch))
        ->toThrow(Exception::class);

    expect($batch->status)->toBe(PayrunBatch::STATUS_PAID);

    // Edge case 7: Timestamps are only set during their respective transitions
    $batch2 = PayrunBatch::factory()->draft()->currentMonth()->create();

    // At draft, no timestamps should be set
    expect($batch2->calculated_at)->toBeNull();
    expect($batch2->approved_at)->toBeNull();
    expect($batch2->paid_at)->toBeNull();

    // Create employees and salary structures
    $employees2 = Employee::factory()->count(2)->create();
    foreach ($employees2 as $employee) {
        SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => $batch2->period_start_date,
        ]);
    }

    // After calculate, only calculated_at should be set
    $payrollService->calculatePayrun($batch2);
    $batch2->refresh();

    expect($batch2->calculated_at)->not->toBeNull();
    expect($batch2->approved_at)->toBeNull();
    expect($batch2->paid_at)->toBeNull();

    // After approve, only approved_at should be newly set
    $payrollService->approve($batch2, $approver);
    $batch2->refresh();

    expect($batch2->calculated_at)->not->toBeNull();
    expect($batch2->approved_at)->not->toBeNull();
    expect($batch2->paid_at)->toBeNull();

    // After mark as paid, all timestamps should be set
    $payrollService->markAsPaid($batch2, $paidBy);
    $batch2->refresh();

    expect($batch2->calculated_at)->not->toBeNull();
    expect($batch2->approved_at)->not->toBeNull();
    expect($batch2->paid_at)->not->toBeNull();

    // Clean up
    $batch->payrollRecords()->forceDelete();
    $batch->forceDelete();
    $batch2->payrollRecords()->forceDelete();
    $batch2->forceDelete();
    foreach ($employees as $employee) {
        $employee->user->forceDelete();
        $employee->forceDelete();
    }
    foreach ($employees2 as $employee) {
        $employee->user->forceDelete();
        $employee->forceDelete();
    }
    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');

/**
 * **Feature: hris-phase3-payroll, Property 5 Additional: User tracking in status transitions**
 *
 * Tests that user information is correctly tracked during status transitions:
 * - approved_by is set when batch is approved
 * - paid_by is set when batch is marked as paid
 * - Different users can approve and mark as paid
 * - User IDs are correctly persisted
 *
 * **Validates: Requirements 2.5, 2.6**
 */
test('Property 5 Additional: User tracking in status transitions', function () {
    $payrollService = app(PayrollCalculationService::class);

    // Run 50 iterations with random user combinations
    for ($i = 0; $i < 50; $i++) {
        $batch = PayrunBatch::factory()->draft()->create();

        // Create random users for approval and payment
        $approver = User::factory()->create();
        $paidBy = User::factory()->create();

        // Create employees and salary structures
        $employees = Employee::factory()->count(fake()->numberBetween(1, 3))->create();
        foreach ($employees as $employee) {
            SalaryStructure::factory()->create([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_start_date,
            ]);
        }

        // Calculate
        $payrollService->calculatePayrun($batch);
        $batch->refresh();

        // Property: approved_by should be null before approval
        expect($batch->approved_by)->toBeNull();

        // Approve with specific user
        $payrollService->approve($batch, $approver);
        $batch->refresh();

        // Property: approved_by must match the approver
        expect($batch->approved_by)->toBe($approver->id);

        // Property: approved_by relationship should work
        expect($batch->approvedBy->id)->toBe($approver->id);

        // Property: paid_by should be null before payment
        expect($batch->paid_by)->toBeNull();

        // Mark as paid with specific user
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        // Property: paid_by must match the paidBy user
        expect($batch->paid_by)->toBe($paidBy->id);

        // Property: paid_by relationship should work
        expect($batch->paidBy->id)->toBe($paidBy->id);

        // Property: approver and paidBy can be different users
        if ($approver->id !== $paidBy->id) {
            expect($batch->approved_by)->not->toBe($batch->paid_by);
        }

        // Clean up
        $batch->payrollRecords()->forceDelete();
        $batch->forceDelete();
        foreach ($employees as $employee) {
            $employee->user->forceDelete();
            $employee->forceDelete();
        }
        $approver->forceDelete();
        $paidBy->forceDelete();
    }
})->group('property-test', 'hris-payroll');
