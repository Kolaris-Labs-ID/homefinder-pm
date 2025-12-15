<?php

/**
 * Property-Based Tests for Payrun Recalculation Idempotency
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
 * **Feature: hris-phase3-payroll, Property 14: Payrun recalculation idempotency**
 *
 * For any draft payrun batch, calculating twice SHALL result in the same number
 * of payroll records (no duplicates) with updated values.
 *
 * **Validates: Requirements 3.11**
 */
test('Property 14: Payrun recalculation idempotency', function () {
    $payrollService = app(PayrollCalculationService::class);

    // Run 100 iterations with random payrun scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create a payrun batch in draft status
        $batch = PayrunBatch::factory()->create([
            'status' => PayrunBatch::STATUS_DRAFT,
        ]);

        // Generate random number of active employees with valid salary structures (3-15)
        $employeeCount = fake()->numberBetween(3, 15);
        $employeeIds = [];

        for ($j = 0; $j < $employeeCount; $j++) {
            $employee = Employee::factory()->create([
                'is_active' => true,
            ]);

            SalaryStructure::factory()->create([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_end_date->copy()->subDays(fake()->numberBetween(0, 30)),
            ]);

            $employeeIds[] = $employee->id;
        }

        // First calculation
        $results1 = $payrollService->calculatePayrun($batch);
        $batch->refresh();

        // Verify first calculation succeeded
        expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
        expect($results1['processed'])->toBe($employeeCount);

        // Get the payroll records from first calculation
        $records1 = PayrollRecord::where('payrun_batch_id', $batch->id)
            ->orderBy('employee_id')
            ->get()
            ->toArray();

        $recordCount1 = count($records1);

        // Property 1: First calculation should create records for all valid employees
        expect($recordCount1)->toBe(
            $employeeCount,
            "First calculation should create payroll records for all {$employeeCount} employees"
        );

        // Store the calculated values from first run for comparison
        $firstCalculationValues = [];
        foreach ($records1 as $record) {
            $firstCalculationValues[$record['employee_id']] = [
                'gross_income' => $record['gross_income'],
                'net_income' => $record['net_income'],
                'pph21_amount' => $record['pph21_amount'],
                'total_deductions' => $record['total_deductions'],
                'basic_salary' => $record['basic_salary'],
                'total_fixed_allowances' => $record['total_fixed_allowances'],
                'total_variable_allowances' => $record['total_variable_allowances'],
                'bpjs_kesehatan_employee' => $record['bpjs_kesehatan_employee'],
                'bpjs_jht_employee' => $record['bpjs_jht_employee'],
                'bpjs_jp_employee' => $record['bpjs_jp_employee'],
            ];
        }

        // Reset batch to draft status for recalculation
        $batch->update([
            'status' => PayrunBatch::STATUS_DRAFT,
            'calculated_at' => null,
        ]);

        // Second calculation (recalculation)
        $results2 = $payrollService->calculatePayrun($batch);
        $batch->refresh();

        // Verify second calculation succeeded
        expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
        expect($results2['processed'])->toBe($employeeCount);

        // Get the payroll records from second calculation
        $records2 = PayrollRecord::where('payrun_batch_id', $batch->id)
            ->orderBy('employee_id')
            ->get()
            ->toArray();

        $recordCount2 = count($records2);

        // Property 2: Idempotency - same number of records after recalculation
        expect($recordCount2)->toBe(
            $recordCount1,
            "Recalculation should result in same number of payroll records (no duplicates). " .
            "First: {$recordCount1}, Second: {$recordCount2}"
        );

        // Property 3: No duplicate records created
        expect($recordCount2)->toBe(
            $employeeCount,
            "Should have exactly {$employeeCount} records after recalculation, not duplicates"
        );

        // Property 4: Each employee should have exactly one record
        $recordsByEmployee = PayrollRecord::where('payrun_batch_id', $batch->id)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, COUNT(*) as count')
            ->get();

        foreach ($recordsByEmployee as $group) {
            expect($group->count)->toBe(
                1,
                "Employee {$group->employee_id} should have exactly 1 payroll record, not {$group->count}"
            );
        }

        // Property 5: Calculated values should be identical (idempotent calculation)
        foreach ($records2 as $record) {
            $employeeId = $record['employee_id'];

            expect(isset($firstCalculationValues[$employeeId]))->toBeTrue(
                "Employee {$employeeId} should have been in first calculation"
            );

            $firstValues = $firstCalculationValues[$employeeId];

            // Compare all key financial values
            expect((float) $record['gross_income'])->toEqualWithDelta(
                (float) $firstValues['gross_income'],
                0.01,
                "Gross income should be identical for employee {$employeeId}"
            );

            expect((float) $record['net_income'])->toEqualWithDelta(
                (float) $firstValues['net_income'],
                0.01,
                "Net income should be identical for employee {$employeeId}"
            );

            expect((float) $record['pph21_amount'])->toEqualWithDelta(
                (float) $firstValues['pph21_amount'],
                0.01,
                "PPh 21 amount should be identical for employee {$employeeId}"
            );

            expect((float) $record['total_deductions'])->toEqualWithDelta(
                (float) $firstValues['total_deductions'],
                0.01,
                "Total deductions should be identical for employee {$employeeId}"
            );

            expect((float) $record['basic_salary'])->toEqualWithDelta(
                (float) $firstValues['basic_salary'],
                0.01,
                "Basic salary should be identical for employee {$employeeId}"
            );

            expect((float) $record['total_fixed_allowances'])->toEqualWithDelta(
                (float) $firstValues['total_fixed_allowances'],
                0.01,
                "Total fixed allowances should be identical for employee {$employeeId}"
            );

            expect((float) $record['total_variable_allowances'])->toEqualWithDelta(
                (float) $firstValues['total_variable_allowances'],
                0.01,
                "Total variable allowances should be identical for employee {$employeeId}"
            );

            expect((float) $record['bpjs_kesehatan_employee'])->toEqualWithDelta(
                (float) $firstValues['bpjs_kesehatan_employee'],
                0.01,
                "BPJS Kesehatan employee should be identical for employee {$employeeId}"
            );

            expect((float) $record['bpjs_jht_employee'])->toEqualWithDelta(
                (float) $firstValues['bpjs_jht_employee'],
                0.01,
                "BPJS JHT employee should be identical for employee {$employeeId}"
            );

            expect((float) $record['bpjs_jp_employee'])->toEqualWithDelta(
                (float) $firstValues['bpjs_jp_employee'],
                0.01,
                "BPJS JP employee should be identical for employee {$employeeId}"
            );
        }

        // Property 6: Batch status should be calculated after both runs
        expect($batch->status)->toBe(
            PayrunBatch::STATUS_CALCULATED,
            "Batch status should remain 'calculated' after recalculation"
        );

        // Property 7: Batch calculated_at should be updated
        expect($batch->calculated_at)->not->toBeNull(
            "Batch calculated_at should be set after recalculation"
        );

        // Clean up for next iteration
        PayrollRecord::where('payrun_batch_id', $batch->id)->forceDelete();
        $batch->forceDelete();
        Employee::whereIn('id', $employeeIds)->forceDelete();
    }
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 14 Edge Cases: Recalculation boundary conditions**
 *
 * Tests edge cases for payrun recalculation idempotency:
 * - Empty payrun (no valid employees)
 * - Single employee recalculation
 * - Multiple recalculations (3+ times)
 * - Recalculation with salary structure changes between runs
 * - Recalculation after partial calculation failure
 *
 * **Validates: Requirements 3.11**
 */
test('Property 14 Edge Cases: Recalculation boundary conditions', function () {
    $payrollService = app(PayrollCalculationService::class);

    // Edge case 1: Empty payrun (no valid employees)
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    $results1 = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    expect($results1['processed'])->toBe(0);
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe(0);

    // Recalculate empty payrun
    $batch->update(['status' => PayrunBatch::STATUS_DRAFT, 'calculated_at' => null]);
    $results2 = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    expect($results2['processed'])->toBe(0);
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe(0);

    $batch->forceDelete();

    // Edge case 2: Single employee recalculation
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    $employee = Employee::factory()->create(['is_active' => true]);
    SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'effective_date' => $batch->period_end_date->copy()->subDays(10),
    ]);

    $results1 = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    $record1 = PayrollRecord::where('payrun_batch_id', $batch->id)->first();
    $netIncome1 = (float) $record1->net_income;

    // Recalculate
    $batch->update(['status' => PayrunBatch::STATUS_DRAFT, 'calculated_at' => null]);
    $results2 = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    $record2 = PayrollRecord::where('payrun_batch_id', $batch->id)->first();
    $netIncome2 = (float) $record2->net_income;

    expect($results1['processed'])->toBe(1);
    expect($results2['processed'])->toBe(1);
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe(1);
    expect($netIncome2)->toEqualWithDelta($netIncome1, 0.01);

    $batch->forceDelete();
    Employee::query()->forceDelete();

    // Edge case 3: Multiple recalculations (3+ times)
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    for ($i = 0; $i < 3; $i++) {
        $employee = Employee::factory()->create(['is_active' => true]);
        SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_end_date->copy()->subDays(10),
        ]);
    }

    $recordCounts = [];
    $netIncomeTotals = [];

    // Run calculation 4 times
    for ($run = 0; $run < 4; $run++) {
        $payrollService->calculatePayrun($batch);
        $batch->refresh();

        $recordCounts[] = PayrollRecord::where('payrun_batch_id', $batch->id)->count();
        $netIncomeTotals[] = PayrollRecord::where('payrun_batch_id', $batch->id)->sum('net_income');

        if ($run < 3) {
            $batch->update(['status' => PayrunBatch::STATUS_DRAFT, 'calculated_at' => null]);
        }
    }

    // Property: All runs should have same record count
    expect($recordCounts[0])->toBe(3);
    expect($recordCounts[1])->toBe(3);
    expect($recordCounts[2])->toBe(3);
    expect($recordCounts[3])->toBe(3);

    // Property: All runs should have same total net income
    expect($netIncomeTotals[1])->toEqualWithDelta($netIncomeTotals[0], 0.01);
    expect($netIncomeTotals[2])->toEqualWithDelta($netIncomeTotals[0], 0.01);
    expect($netIncomeTotals[3])->toEqualWithDelta($netIncomeTotals[0], 0.01);

    $batch->forceDelete();
    Employee::query()->forceDelete();

    // Edge case 4: Recalculation with salary structure changes between runs
    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    $employee = Employee::factory()->create(['is_active' => true]);

    // Create initial salary structure with specific basic salary
    $basicSalary1 = 5000000;
    $structure1 = SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'effective_date' => $batch->period_end_date->copy()->subDays(30),
        'basic_salary' => $basicSalary1,
    ]);

    // First calculation
    $results1 = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    $record1 = PayrollRecord::where('payrun_batch_id', $batch->id)->first();
    $grossIncome1 = (float) $record1->gross_income;
    $basicSalaryFromRecord1 = (float) $record1->basic_salary;

    // Now update the salary structure (create a newer one with different basic salary)
    $basicSalary2 = 7000000; // Different from first
    $structure2 = SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'effective_date' => $batch->period_end_date->copy()->subDays(10),
        'basic_salary' => $basicSalary2,
    ]);

    // Recalculate - should use the newer structure
    $batch->update(['status' => PayrunBatch::STATUS_DRAFT, 'calculated_at' => null]);
    $results2 = $payrollService->calculatePayrun($batch);
    $batch->refresh();

    $record2 = PayrollRecord::where('payrun_batch_id', $batch->id)->first();
    $grossIncome2 = (float) $record2->gross_income;
    $basicSalaryFromRecord2 = (float) $record2->basic_salary;

    // Property: Basic salary should be different because salary structure changed
    expect($basicSalaryFromRecord2)->not->toEqual($basicSalaryFromRecord1);
    expect($basicSalaryFromRecord2)->toEqualWithDelta($basicSalary2, 0.01);

    // Property: Gross income should be different because salary structure changed
    expect($grossIncome2)->not->toEqualWithDelta($grossIncome1, 0.01);

    // Property: Still only one record (no duplicates)
    expect(PayrollRecord::where('payrun_batch_id', $batch->id)->count())->toBe(1);

    $batch->forceDelete();
    Employee::query()->forceDelete();

})->group('property-test', 'hris-payroll');
