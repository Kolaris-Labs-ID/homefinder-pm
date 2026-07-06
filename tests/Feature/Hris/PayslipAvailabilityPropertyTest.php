<?php

/**
 * Property-Based Tests for Payslip Availability After Payment
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
use App\Services\Hris\PayslipService;

// Idempotent: one salary structure per (employee, effective_date). Multiple
// batches for one employee can share a month; the unique constraint forbids
// duplicate (employee_id, effective_date) rows, so reuse the existing one.
function ensureSalaryStructureForBatch(array $attrs): SalaryStructure
{
    $employeeId = $attrs['employee_id'];
    $date = \Illuminate\Support\Carbon::parse($attrs['effective_date'])->toDateString();

    $existing = SalaryStructure::where('employee_id', $employeeId)
        ->whereDate('effective_date', $date)
        ->first();

    return $existing ?? SalaryStructure::factory()->create(array_merge($attrs, [
        'employee_id' => $employeeId,
        'effective_date' => $date,
    ]));
}

beforeEach(function () {
    // Clean up before each test
    PayrunBatch::query()->delete();
    PayrollRecord::query()->delete();
    SalaryStructure::query()->delete();
});

/**
 * **Feature: hris-phase3-payroll, Property 17: Payslip availability after payment**
 *
 * For any payrun batch marked as paid, all associated payroll records SHALL be
 * accessible as payslips to their respective employees.
 *
 * **Validates: Requirements 5.1**
 */
test('Property 17: Payslip availability after payment', function () {
    $payrollService = app(PayrollCalculationService::class);
    $payslipService = app(PayslipService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Run 100 iterations with random payrun batch scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create a fresh payrun batch for each iteration
        $batch = PayrunBatch::factory()->draft()->create();

        // Create random number of employees with salary structures
        $employeeCount = fake()->numberBetween(1, 10);
        $employees = Employee::factory()->count($employeeCount)->create();

        foreach ($employees as $employee) {
            ensureSalaryStructureForBatch([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_start_date,
            ]);
        }

        // Calculate payroll
        $payrollService->calculatePayrun($batch);
        $batch->refresh();

        // Property: Before payment, payslips should NOT be available
        $recordsBeforePayment = $batch->payrollRecords()->get();
        foreach ($recordsBeforePayment as $record) {
            expect($payslipService->isPayslipAvailable($record))->toBeFalse();
        }

        // Approve the batch
        $payrollService->approve($batch, $approver);
        $batch->refresh();

        // Property: After approval but before payment, payslips should NOT be available
        $recordsAfterApproval = $batch->payrollRecords()->get();
        foreach ($recordsAfterApproval as $record) {
            expect($payslipService->isPayslipAvailable($record))->toBeFalse();
        }

        // Mark as paid
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        // Property: After payment, ALL payroll records should be accessible as payslips
        $recordsAfterPayment = $batch->payrollRecords()->get();
        expect($recordsAfterPayment->count())->toBe($employeeCount);

        foreach ($recordsAfterPayment as $record) {
            // Property: isPayslipAvailable should return true
            expect($payslipService->isPayslipAvailable($record))->toBeTrue();

            // Property: Payslip should be retrievable via getEmployeePayslips
            $employeePayslips = $payslipService->getEmployeePayslips($record->employee);
            expect($employeePayslips->contains('id', $record->id))->toBeTrue();

            // Property: Payslip summary should be retrievable
            $summary = $payslipService->getPayslipSummary($record);
            expect($summary)->toHaveKeys([
                'id',
                'period',
                'earnings',
                'deductions',
                'company_contributions',
                'net_income',
            ]);

            // Property: Payslip HTML should be generatable
            $html = $payslipService->generate($record);
            expect($html)->toBeString();
            expect(strlen($html))->toBeGreaterThan(0);
        }

        // Property: Each employee should have exactly one payslip for this batch
        foreach ($employees as $employee) {
            $employeePayslips = $payslipService->getEmployeePayslips($employee);
            $payslipsForThisBatch = $employeePayslips->filter(function ($payslip) use ($batch) {
                return $payslip->payrun_batch_id === $batch->id;
            });
            expect($payslipsForThisBatch->count())->toBe(1);
        }

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
 * **Feature: hris-phase3-payroll, Property 17 Edge Cases: Payslip availability boundary conditions**
 *
 * Tests edge cases for payslip availability:
 * - Payslips are NOT available for draft batches
 * - Payslips are NOT available for calculated batches
 * - Payslips are NOT available for approved batches
 * - Payslips ARE available for paid batches
 * - Multiple batches can have payslips available simultaneously
 * - Payslips remain available after batch is paid
 * - Payslip data is consistent across multiple retrievals
 *
 * **Validates: Requirements 5.1**
 */
test('Property 17 Edge Cases: Payslip availability boundary conditions', function () {
    $payrollService = app(PayrollCalculationService::class);
    $payslipService = app(PayslipService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Create an employee with salary structure
    $employee = Employee::factory()->create();

    // Edge case 1: Draft batch - payslips not available
    $draftBatch = PayrunBatch::factory()->draft()->create();
    ensureSalaryStructureForBatch([
        'employee_id' => $employee->id,
        'effective_date' => $draftBatch->period_start_date,
    ]);

    $payrollService->calculatePayrun($draftBatch);
    $draftBatch->refresh();

    $draftRecord = $draftBatch->payrollRecords()->first();
    expect($payslipService->isPayslipAvailable($draftRecord))->toBeFalse();

    // Edge case 2: Calculated batch - payslips not available
    $calculatedBatch = PayrunBatch::factory()->draft()->create();
    ensureSalaryStructureForBatch([
        'employee_id' => $employee->id,
        'effective_date' => $calculatedBatch->period_start_date,
    ]);

    $payrollService->calculatePayrun($calculatedBatch);
    $calculatedBatch->refresh();

    $calculatedRecord = $calculatedBatch->payrollRecords()->first();
    expect($payslipService->isPayslipAvailable($calculatedRecord))->toBeFalse();

    // Edge case 3: Approved batch - payslips not available
    $approvedBatch = PayrunBatch::factory()->draft()->create();
    ensureSalaryStructureForBatch([
        'employee_id' => $employee->id,
        'effective_date' => $approvedBatch->period_start_date,
    ]);

    $payrollService->calculatePayrun($approvedBatch);
    $payrollService->approve($approvedBatch, $approver);
    $approvedBatch->refresh();

    $approvedRecord = $approvedBatch->payrollRecords()->first();
    expect($payslipService->isPayslipAvailable($approvedRecord))->toBeFalse();

    // Edge case 4: Paid batch - payslips ARE available
    $paidBatch = PayrunBatch::factory()->draft()->create();
    ensureSalaryStructureForBatch([
        'employee_id' => $employee->id,
        'effective_date' => $paidBatch->period_start_date,
    ]);

    $payrollService->calculatePayrun($paidBatch);
    $payrollService->approve($paidBatch, $approver);
    $payrollService->markAsPaid($paidBatch, $paidBy);
    $paidBatch->refresh();

    $paidRecord = $paidBatch->payrollRecords()->first();
    expect($payslipService->isPayslipAvailable($paidRecord))->toBeTrue();

    // Edge case 5: Multiple paid batches - all payslips available
    $paidBatch2 = PayrunBatch::factory()->draft()->create();
    ensureSalaryStructureForBatch([
        'employee_id' => $employee->id,
        'effective_date' => $paidBatch2->period_start_date,
    ]);

    $payrollService->calculatePayrun($paidBatch2);
    $payrollService->approve($paidBatch2, $approver);
    $payrollService->markAsPaid($paidBatch2, $paidBy);
    $paidBatch2->refresh();

    $paidRecord2 = $paidBatch2->payrollRecords()->first();

    // Both paid batches should have available payslips
    expect($payslipService->isPayslipAvailable($paidRecord))->toBeTrue();
    expect($payslipService->isPayslipAvailable($paidRecord2))->toBeTrue();

    // Employee should have 2 payslips
    $employeePayslips = $payslipService->getEmployeePayslips($employee);
    expect($employeePayslips->count())->toBe(2);

    // Edge case 6: Payslips remain available after batch is paid
    $payslipSummary1 = $payslipService->getPayslipSummary($paidRecord);
    sleep(1); // Small delay to ensure time passes
    $payslipSummary1Again = $payslipService->getPayslipSummary($paidRecord);

    // Summaries should be identical
    expect($payslipSummary1)->toBe($payslipSummary1Again);

    // Edge case 7: Payslip data is consistent across multiple retrievals
    $html1 = $payslipService->generate($paidRecord);
    $html2 = $payslipService->generate($paidRecord);

    // HTML should be identical (deterministic)
    expect($html1)->toBe($html2);

    // Clean up
    $draftBatch->payrollRecords()->forceDelete();
    $draftBatch->forceDelete();
    $calculatedBatch->payrollRecords()->forceDelete();
    $calculatedBatch->forceDelete();
    $approvedBatch->payrollRecords()->forceDelete();
    $approvedBatch->forceDelete();
    $paidBatch->payrollRecords()->forceDelete();
    $paidBatch->forceDelete();
    $paidBatch2->payrollRecords()->forceDelete();
    $paidBatch2->forceDelete();
    $employee->user->forceDelete();
    $employee->forceDelete();
    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');

/**
 * **Feature: hris-phase3-payroll, Property 17 Additional: Payslip employee scoping**
 *
 * Tests that payslips are correctly scoped to employees:
 * - Employee can only retrieve their own payslips
 * - Payslips from different employees are not mixed
 * - getEmployeePayslips returns only payslips for that employee
 * - Payslip data is accessible only to the owning employee
 *
 * **Validates: Requirements 5.1, 5.5**
 */
test('Property 17 Additional: Payslip employee scoping', function () {
    $payrollService = app(PayrollCalculationService::class);
    $payslipService = app(PayslipService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Run 50 iterations with random employee scenarios
    for ($i = 0; $i < 50; $i++) {
        // Create multiple employees
        $employeeCount = fake()->numberBetween(2, 5);
        $employees = Employee::factory()->count($employeeCount)->create();

        // Create a paid batch with payslips for all employees
        $batch = PayrunBatch::factory()->draft()->create();

        foreach ($employees as $employee) {
            ensureSalaryStructureForBatch([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_start_date,
            ]);
        }

        $payrollService->calculatePayrun($batch);
        $payrollService->approve($batch, $approver);
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        // Property: Each employee should only see their own payslips
        foreach ($employees as $employee) {
            $employeePayslips = $payslipService->getEmployeePayslips($employee);

            // All payslips should belong to this employee
            foreach ($employeePayslips as $payslip) {
                expect($payslip->employee_id)->toBe($employee->id);
            }

            // Should have exactly 1 payslip for this batch
            $payslipsForThisBatch = $employeePayslips->filter(function ($payslip) use ($batch) {
                return $payslip->payrun_batch_id === $batch->id;
            });
            expect($payslipsForThisBatch->count())->toBe(1);
        }

        // Property: Payslips from different employees should not be mixed
        $allPayslips = PayrollRecord::whereIn('employee_id', $employees->pluck('id'))
            ->where('payrun_batch_id', $batch->id)
            ->get();

        expect($allPayslips->count())->toBe($employeeCount);

        // Each employee should have exactly one record
        foreach ($employees as $employee) {
            $employeeRecords = $allPayslips->where('employee_id', $employee->id);
            expect($employeeRecords->count())->toBe(1);
        }

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
