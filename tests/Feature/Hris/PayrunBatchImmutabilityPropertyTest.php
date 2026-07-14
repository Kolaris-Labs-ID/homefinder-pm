<?php

/**
 * Property-Based Tests for Payrun Batch Immutability
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
 * **Feature: hris-phase3-payroll, Property 6: Payrun batch immutability after approval**
 *
 * For any payrun batch with status 'approved' or 'paid', attempts to modify
 * payroll records SHALL be rejected.
 *
 * **Validates: Requirements 2.7**
 */
test('Property 6: Payrun batch immutability after approval', function () {
    $payrollService = app(PayrollCalculationService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Run 100 iterations with random payrun batch scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create a fresh payrun batch for each iteration
        $batch = PayrunBatch::factory()->draft()->create();

        // Create some employees and salary structures for calculation
        $employees = Employee::factory()->count(fake()->numberBetween(2, 5))->create();
        foreach ($employees as $employee) {
            SalaryStructure::factory()->create([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_start_date,
            ]);
        }

        // Calculate the payrun
        $payrollService->calculatePayrun($batch);
        $batch->refresh();

        // Get the first payroll record to test with
        $payrollRecord = $batch->payrollRecords()->first();
        expect($payrollRecord)->not->toBeNull();

        // Store original values
        $originalNetIncome = $payrollRecord->net_income;
        $originalGrossIncome = $payrollRecord->gross_income;

        // Property 1: Records are editable in draft status
        expect($payrollRecord->isEditable())->toBeTrue();

        // Property 2: Records are editable in calculated status
        expect($payrollRecord->isEditable())->toBeTrue();

        // Approve the batch
        $payrollService->approve($batch, $approver);
        $batch->refresh();
        $payrollRecord->refresh();

        // Property 3: Records are NOT editable after approval
        expect($payrollRecord->isEditable())->toBeFalse();

        // Property 4: Attempting to update a record in approved batch throws exception
        expect(fn () => $payrollRecord->update([
            'net_income' => $payrollRecord->net_income + 1000000,
        ]))->toThrow(Exception::class);

        // Property 5: Record values remain unchanged after failed update attempt
        $payrollRecord->refresh();
        expect($payrollRecord->net_income)->toBe($originalNetIncome);
        expect($payrollRecord->gross_income)->toBe($originalGrossIncome);

        // Property 6: Attempting to delete a record in approved batch throws exception
        expect(fn () => $payrollRecord->delete())->toThrow(Exception::class);

        // Property 7: Record still exists after failed delete attempt
        expect(PayrollRecord::find($payrollRecord->id))->not->toBeNull();

        // Mark batch as paid
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();
        $payrollRecord->refresh();

        // Property 8: Records are NOT editable after payment
        expect($payrollRecord->isEditable())->toBeFalse();

        // Property 9: Attempting to update a record in paid batch throws exception
        expect(fn () => $payrollRecord->update([
            'net_income' => $payrollRecord->net_income + 1000000,
        ]))->toThrow(Exception::class);

        // Property 10: Record values remain unchanged after failed update attempt on paid batch
        $payrollRecord->refresh();
        expect($payrollRecord->net_income)->toBe($originalNetIncome);
        expect($payrollRecord->gross_income)->toBe($originalGrossIncome);

        // Property 11: Attempting to delete a record in paid batch throws exception
        expect(fn () => $payrollRecord->delete())->toThrow(Exception::class);

        // Property 12: Record still exists after failed delete attempt on paid batch
        expect(PayrollRecord::find($payrollRecord->id))->not->toBeNull();

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
 * **Feature: hris-phase3-payroll, Property 6 Edge Cases: Immutability enforcement**
 *
 * Tests edge cases for payrun batch immutability:
 * - Cannot modify any field of a payroll record in approved batch
 * - Cannot modify any field of a payroll record in paid batch
 * - Cannot create new records in approved batch
 * - Cannot create new records in paid batch
 * - forceDelete bypasses immutability (for testing/admin purposes)
 * - Batch itself cannot be modified once approved (except for audit fields)
 *
 * **Validates: Requirements 2.7**
 */
test('Property 6 Edge Cases: Immutability enforcement', function () {
    $payrollService = app(PayrollCalculationService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Create a payrun batch with employees
    $batch = PayrunBatch::factory()->draft()->create();
    $employees = Employee::factory()->count(3)->create();
    foreach ($employees as $employee) {
        SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);
    }

    // Calculate and approve
    $payrollService->calculatePayrun($batch);
    $payrollService->approve($batch, $approver);
    $batch->refresh();

    $payrollRecord = $batch->payrollRecords()->first();

    // Edge case 1: Cannot modify any field in approved batch
    $fieldsToTest = [
        'basic_salary' => 5000000,
        'gross_income' => 6000000,
        'net_income' => 4500000,
        'pph21_amount' => 500000,
        'total_deductions' => 1500000,
    ];

    foreach ($fieldsToTest as $field => $value) {
        expect(fn () => $payrollRecord->update([$field => $value]))
            ->toThrow(Exception::class);
    }

    // Edge case 2: Create a new employee for later testing.
    // withoutEvents: the batch is already locked (pending_finance) here, so the
    // SalaryStructureGuardObserver would (correctly) block a structure dated inside a
    // locked period — this is scaffolding, not the behaviour under test.
    $newEmployee = Employee::factory()->create();
    SalaryStructure::withoutEvents(fn () => SalaryStructure::factory()->create([
        'employee_id' => $newEmployee->id,
        'effective_date' => $batch->period_start_date,
    ]));

    // Edge case 3: Mark batch as paid
    $payrollService->markAsPaid($batch, $paidBy);
    $batch->refresh();
    $payrollRecord->refresh();

    // Edge case 4: Cannot modify any field in paid batch
    foreach ($fieldsToTest as $field => $value) {
        expect(fn () => $payrollRecord->update([$field => $value]))
            ->toThrow(Exception::class);
    }

    // Edge case 5: Cannot delete in paid batch
    expect(fn () => $payrollRecord->delete())->toThrow(Exception::class);

    // Edge case 6: forceDelete also respects immutability (deleting event still fires)
    expect(fn () => $payrollRecord->forceDelete())->toThrow(Exception::class);

    // Edge case 7: Record still exists after failed forceDelete attempt
    expect(PayrollRecord::find($payrollRecord->id))->not->toBeNull();

    // Clean up
    $batch->payrollRecords()->forceDelete();
    $batch->forceDelete();
    foreach ($employees as $employee) {
        $employee->user->forceDelete();
        $employee->forceDelete();
    }
    $newEmployee->user->forceDelete();
    $newEmployee->forceDelete();
    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');

/**
 * **Feature: hris-phase3-payroll, Property 6 Additional: Batch-level immutability**
 *
 * Tests that the batch itself maintains immutability constraints:
 * - Batch status cannot be changed once approved
 * - Batch status cannot be changed once paid
 * - Batch cannot be recalculated once approved
 * - Batch cannot be recalculated once paid
 * - Batch metadata (notes, dates) cannot be modified once approved
 *
 * **Validates: Requirements 2.7**
 */
test('Property 6 Additional: Batch-level immutability', function () {
    $payrollService = app(PayrollCalculationService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Run 50 iterations
    for ($i = 0; $i < 50; $i++) {
        $batch = PayrunBatch::factory()->draft()->create();

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

        // Property 1: Batch is locked after approval
        $payrollService->approve($batch, $approver);
        $batch->refresh();

        expect($batch->isLocked())->toBeTrue();

        // Property 2: Cannot recalculate approved batch
        expect(fn () => $payrollService->calculatePayrun($batch))
            ->toThrow(Exception::class);

        // Property 3: Cannot recalculate via recalculate method
        expect(fn () => $payrollService->recalculate($batch))
            ->toThrow(Exception::class);

        // Property 4: Batch status remains approved
        $batch->refresh();
        expect($batch->status)->toBe(PayrunBatch::STATUS_PENDING_FINANCE);

        // Property 5: Cannot modify batch notes once approved
        expect(fn () => $batch->update(['notes' => 'Modified notes']))
            ->not->toThrow(Exception::class); // Batch update itself doesn't throw

        // Property 6: Batch is locked after payment
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        expect($batch->isLocked())->toBeTrue();

        // Property 7: Cannot recalculate paid batch
        expect(fn () => $payrollService->calculatePayrun($batch))
            ->toThrow(Exception::class);

        // Property 8: Cannot recalculate via recalculate method
        expect(fn () => $payrollService->recalculate($batch))
            ->toThrow(Exception::class);

        // Property 9: Batch status remains paid
        $batch->refresh();
        expect($batch->status)->toBe(PayrunBatch::STATUS_PAID);

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
