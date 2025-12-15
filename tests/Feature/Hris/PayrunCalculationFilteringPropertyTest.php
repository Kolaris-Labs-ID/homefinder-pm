<?php

/**
 * Property-Based Tests for Payrun Calculation Filtering
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\PayrollCalculationService;

beforeEach(function () {
    // Clean up before each test
    PayrollRecord::query()->delete();
    PayrunBatch::query()->delete();
    SalaryStructure::query()->delete();
    Employee::query()->delete();
});

/**
 * **Feature: hris-phase3-payroll, Property 7: Payrun calculation processes only valid employees**
 *
 * For any payrun batch calculation, the system SHALL create payroll records
 * only for active employees who have a valid salary structure for the payrun period.
 *
 * **Validates: Requirements 2.3**
 */
test('Property 7: Payrun calculation processes only valid employees', function () {
    $payrollService = app(PayrollCalculationService::class);

    // Run 100 iterations with random employee scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create a payrun batch
        $batch = PayrunBatch::factory()->create([
            'status' => PayrunBatch::STATUS_DRAFT,
        ]);

        // Generate random number of active employees (5-20)
        $totalActiveEmployees = fake()->numberBetween(5, 20);

        // Track which active employees should be processed
        $validEmployeeIds = [];
        $invalidActiveEmployeeIds = [];

        // Create active employees with various states
        for ($j = 0; $j < $totalActiveEmployees; $j++) {
            // Randomly decide if this active employee should have a valid salary structure
            $shouldHaveValidStructure = fake()->boolean(70); // 70% chance of having valid structure

            $employee = Employee::factory()->create([
                'is_active' => true,
            ]);

            if ($shouldHaveValidStructure) {
                // Create a salary structure with effective_date before or on the payrun period end
                SalaryStructure::factory()->create([
                    'employee_id' => $employee->id,
                    'effective_date' => $batch->period_end_date->copy()->subDays(fake()->numberBetween(0, 30)),
                ]);

                $validEmployeeIds[] = $employee->id;
            } else {
                // Active employee but without a valid salary structure
                // Either no structure or structure with future effective_date
                if (fake()->boolean(50)) {
                    // Create a structure with future effective_date (invalid for this payrun)
                    SalaryStructure::factory()->create([
                        'employee_id' => $employee->id,
                        'effective_date' => $batch->period_end_date->copy()->addDays(fake()->numberBetween(1, 30)),
                    ]);
                }
                // else: no structure at all

                $invalidActiveEmployeeIds[] = $employee->id;
            }
        }

        // Also create some inactive employees (they should be completely ignored)
        $inactiveEmployeeIds = [];
        $numInactiveEmployees = fake()->numberBetween(0, 5);
        for ($j = 0; $j < $numInactiveEmployees; $j++) {
            $employee = Employee::factory()->create([
                'is_active' => false,
            ]);

            // Even if we create a salary structure, it shouldn't be processed
            if (fake()->boolean(50)) {
                SalaryStructure::factory()->create([
                    'employee_id' => $employee->id,
                    'effective_date' => $batch->period_end_date->copy()->subDays(10),
                ]);
            }

            $inactiveEmployeeIds[] = $employee->id;
        }

        // Execute the payrun calculation
        $results = $payrollService->calculatePayrun($batch);

        // Refresh batch to get updated status
        $batch->refresh();

        // Property 1: Batch status must be updated to 'calculated'
        expect($batch->status)->toBe(
            PayrunBatch::STATUS_CALCULATED,
            "Batch status should be updated to 'calculated' after processing"
        );

        // Property 2: Batch calculated_at timestamp must be set
        expect($batch->calculated_at)->not->toBeNull(
            "Batch calculated_at timestamp should be set"
        );

        // Property 3: Only valid active employees should have payroll records created
        $createdRecords = PayrollRecord::where('payrun_batch_id', $batch->id)->get();

        foreach ($createdRecords as $record) {
            expect(in_array($record->employee_id, $validEmployeeIds))->toBeTrue(
                "Payroll record created for employee {$record->employee_id} should be in valid employees list"
            );

            // Property 4: Each created record should have an associated active employee
            $employee = Employee::find($record->employee_id);
            expect($employee->is_active)->toBeTrue(
                "Employee {$record->employee_id} should be active"
            );

            // Property 5: Each created record should have a valid salary structure
            $salaryStructure = $payrollService->getActiveSalaryStructure(
                $employee,
                $batch->period_end_date
            );
            expect($salaryStructure)->not->toBeNull(
                "Employee {$record->employee_id} should have a valid salary structure"
            );
        }

        // Property 6: Invalid active employees should NOT have payroll records
        foreach ($invalidActiveEmployeeIds as $invalidEmployeeId) {
            $recordExists = PayrollRecord::where('payrun_batch_id', $batch->id)
                ->where('employee_id', $invalidEmployeeId)
                ->exists();

            expect($recordExists)->toBeFalse(
                "Payroll record should NOT be created for active employee without valid structure {$invalidEmployeeId}"
            );
        }

        // Property 7: Inactive employees should NOT have payroll records
        foreach ($inactiveEmployeeIds as $inactiveEmployeeId) {
            $recordExists = PayrollRecord::where('payrun_batch_id', $batch->id)
                ->where('employee_id', $inactiveEmployeeId)
                ->exists();

            expect($recordExists)->toBeFalse(
                "Payroll record should NOT be created for inactive employee {$inactiveEmployeeId}"
            );
        }

        // Property 8: Number of processed records should match number of valid employees
        expect($createdRecords->count())->toBe(
            count($validEmployeeIds),
            "Number of created payroll records should equal number of valid employees. " .
            "Expected: " . count($validEmployeeIds) . ", Got: " . $createdRecords->count()
        );

        // Property 9: Results array should reflect the processing
        // processed = valid employees with salary structures
        // skipped = active employees without valid salary structures
        expect($results['processed'])->toBe(
            count($validEmployeeIds),
            "Results['processed'] should equal number of valid employees"
        );

        expect($results['skipped'])->toBe(
            count($invalidActiveEmployeeIds),
            "Results['skipped'] should equal number of active employees without valid salary structures"
        );

        // Clean up for next iteration
        PayrollRecord::where('payrun_batch_id', $batch->id)->forceDelete();
        $batch->forceDelete();
        Employee::whereIn('id', array_merge($validEmployeeIds, $invalidActiveEmployeeIds, $inactiveEmployeeIds))->forceDelete();
    }
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 7 Edge Cases: Payrun filtering boundary conditions**
 *
 * Tests edge cases for payrun calculation filtering:
 * - Empty employee list (no employees in system)
 * - All employees are inactive
 * - All employees are active but have no salary structures
 * - All employees are valid
 * - Salary structure effective_date exactly equals payrun period_end_date
 * - Multiple salary structures for same employee (should use most recent)
 *
 * **Validates: Requirements 2.3**
 */
test('Property 7 Edge Cases: Payrun filtering boundary conditions', function () {
    $payrollService = app(PayrollCalculationService::class);

    // Edge case 1: Empty employee list (no employees in system)
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    $results = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    expect($results['processed'])->toBe(0);
    expect($results['skipped'])->toBe(0);
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe(0);

    $batch->forceDelete();

    // Edge case 2: All employees are inactive
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $employee = Employee::factory()->create([
            'is_active' => false,
        ]);

        SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_end_date->copy()->subDays(10),
        ]);
    }

    $results = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    expect($results['processed'])->toBe(0);
    expect($results['skipped'])->toBe(0); // Inactive employees are not even queried
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe(0);

    $batch->forceDelete();
    Employee::query()->forceDelete();

    // Edge case 3: All employees are active but have no salary structures
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    for ($i = 0; $i < 5; $i++) {
        Employee::factory()->create([
            'is_active' => true,
        ]);
    }

    $results = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    expect($results['processed'])->toBe(0);
    expect($results['skipped'])->toBe(5);
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe(0);

    $batch->forceDelete();
    Employee::query()->forceDelete();

    // Edge case 4: All employees are valid
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    $validCount = 5;
    for ($i = 0; $i < $validCount; $i++) {
        $employee = Employee::factory()->create([
            'is_active' => true,
        ]);

        SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_end_date->copy()->subDays(10),
        ]);
    }

    $results = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    expect($results['processed'])->toBe($validCount);
    expect($results['skipped'])->toBe(0);
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe($validCount);

    $batch->forceDelete();
    Employee::query()->forceDelete();

    // Edge case 5: Salary structure effective_date exactly equals payrun period_end_date
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    $employee = Employee::factory()->create([
        'is_active' => true,
    ]);

    // Create structure with effective_date exactly on period_end_date
    SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'effective_date' => $batch->period_end_date,
    ]);

    $results = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    expect($results['processed'])->toBe(1);
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe(1);

    $batch->forceDelete();
    Employee::query()->forceDelete();

    // Edge case 6: Multiple salary structures for same employee (should use most recent)
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    $employee = Employee::factory()->create([
        'is_active' => true,
    ]);

    // Create multiple structures with different effective dates
    $olderStructure = SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'effective_date' => $batch->period_end_date->copy()->subDays(60),
        'basic_salary' => 5000000,
    ]);

    $newerStructure = SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'effective_date' => $batch->period_end_date->copy()->subDays(10),
        'basic_salary' => 6000000,
    ]);

    $results = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    expect($results['processed'])->toBe(1);

    $record = PayrollRecord::where('payrun_batch_id', $batch->id)->first();
    expect($record)->not->toBeNull();

    // Property: Should use the newer structure (basic_salary should be 6000000)
    expect((float) $record->basic_salary)->toEqualWithDelta(
        6000000,
        0.01,
        "Should use the most recent salary structure"
    );

    $batch->forceDelete();
    Employee::query()->forceDelete();
})->group('property-test', 'hris-payroll');
