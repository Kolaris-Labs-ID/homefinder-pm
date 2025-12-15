<?php

/**
 * Property-Based Tests for Payslip Content Completeness
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

beforeEach(function () {
    // Clean up before each test
    PayrunBatch::query()->delete();
    PayrollRecord::query()->delete();
    SalaryStructure::query()->delete();
});

/**
 * **Feature: hris-phase3-payroll, Property 18: Payslip content completeness**
 *
 * For any generated payslip, the output SHALL contain gross_income, all BPJS components,
 * pph21_amount, total_deductions, and net_income.
 *
 * **Validates: Requirements 5.2**
 */
test('Property 18: Payslip content completeness', function () {
    $payrollService = app(PayrollCalculationService::class);
    $payslipService = app(PayslipService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Run 100 iterations with random payroll record scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create a fresh payrun batch for each iteration
        $batch = PayrunBatch::factory()->draft()->create();

        // Create random number of employees with salary structures
        $employeeCount = fake()->numberBetween(1, 5);
        $employees = Employee::factory()->count($employeeCount)->create();

        foreach ($employees as $employee) {
            SalaryStructure::factory()->create([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_start_date,
            ]);
        }

        // Calculate payroll
        $payrollService->calculatePayrun($batch);
        $batch->refresh();

        // Approve and mark as paid
        $payrollService->approve($batch, $approver);
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        // Get all payroll records for this batch
        $records = $batch->payrollRecords()->get();

        foreach ($records as $record) {
            // Generate HTML payslip
            $html = $payslipService->generate($record);

            // Property: HTML should be a non-empty string
            expect($html)->toBeString();
            expect(strlen($html))->toBeGreaterThan(0);

            // Property: HTML should contain DOCTYPE and basic structure
            expect($html)->toContain('<!DOCTYPE html>');
            expect($html)->toContain('SLIP GAJI');
            expect($html)->toContain('PAYSLIP');

            // Property: HTML should contain employee information section
            expect($html)->toContain('Nama / Name:');
            expect($html)->toContain('Tanggal Bayar / Pay Date:');
            expect($html)->toContain($batch->period_start_date->format('d M Y'));
            expect($html)->toContain($batch->period_end_date->format('d M Y'));

            // Property: HTML should contain EARNINGS section with all components
            expect($html)->toContain('PENDAPATAN');
            expect($html)->toContain('EARNINGS');
            expect($html)->toContain('Gaji Pokok');
            expect($html)->toContain('Basic Salary');
            expect($html)->toContain('Tunjangan Tetap');
            expect($html)->toContain('Fixed Allowances');
            expect($html)->toContain('Tunjangan Tidak Tetap');
            expect($html)->toContain('Variable Allowances');
            expect($html)->toContain('Total Pendapatan Kotor');
            expect($html)->toContain('Gross Income');

            // Property: HTML should contain formatted gross income value
            $grossIncomeFormatted = number_format($record->gross_income, 0, ',', '.');
            expect($html)->toContain($grossIncomeFormatted);

            // Property: HTML should contain DEDUCTIONS section with all components
            expect($html)->toContain('POTONGAN');
            expect($html)->toContain('DEDUCTIONS');
            expect($html)->toContain('BPJS Kesehatan');
            expect($html)->toContain('BPJS JHT');
            expect($html)->toContain('BPJS JP');
            expect($html)->toContain('PPh 21');

            // Property: HTML should contain formatted BPJS employee contributions
            $bpjsKesehatanFormatted = number_format($record->bpjs_kesehatan_employee, 0, ',', '.');
            expect($html)->toContain($bpjsKesehatanFormatted);

            $bpjsJhtFormatted = number_format($record->bpjs_jht_employee, 0, ',', '.');
            expect($html)->toContain($bpjsJhtFormatted);

            $bpjsJpFormatted = number_format($record->bpjs_jp_employee, 0, ',', '.');
            expect($html)->toContain($bpjsJpFormatted);

            // Property: HTML should contain formatted PPh 21 amount
            $pph21Formatted = number_format($record->pph21_amount, 0, ',', '.');
            expect($html)->toContain($pph21Formatted);

            // Property: HTML should contain total deductions
            expect($html)->toContain('Total Potongan');
            expect($html)->toContain('Total Deductions');
            $totalDeductionsFormatted = number_format($record->total_deductions, 0, ',', '.');
            expect($html)->toContain($totalDeductionsFormatted);

            // Property: HTML should contain COMPANY CONTRIBUTIONS section
            expect($html)->toContain('KONTRIBUSI PERUSAHAAN');
            expect($html)->toContain('COMPANY CONTRIBUTIONS');

            // Property: HTML should contain all company BPJS contributions
            $bpjsKesehatanCompanyFormatted = number_format($record->bpjs_kesehatan_company, 0, ',', '.');
            expect($html)->toContain($bpjsKesehatanCompanyFormatted);

            $bpjsJhtCompanyFormatted = number_format($record->bpjs_jht_company, 0, ',', '.');
            expect($html)->toContain($bpjsJhtCompanyFormatted);

            $bpjsJpCompanyFormatted = number_format($record->bpjs_jp_company, 0, ',', '.');
            expect($html)->toContain($bpjsJpCompanyFormatted);

            $bpjsJkkCompanyFormatted = number_format($record->bpjs_jkk_company, 0, ',', '.');
            expect($html)->toContain($bpjsJkkCompanyFormatted);

            $bpjsJkmCompanyFormatted = number_format($record->bpjs_jkm_company, 0, ',', '.');
            expect($html)->toContain($bpjsJkmCompanyFormatted);

            // Property: HTML should contain NET INCOME section
            expect($html)->toContain('GAJI BERSIH');
            expect($html)->toContain('NET INCOME');

            // Property: HTML should contain formatted net income value
            $netIncomeFormatted = number_format($record->net_income, 0, ',', '.');
            expect($html)->toContain($netIncomeFormatted);

            // Property: HTML should contain footer
            expect($html)->toContain('Dokumen ini dibuat secara otomatis');
            expect($html)->toContain('automatically generated');

            // Property: Payslip summary should contain all required keys
            $summary = $payslipService->getPayslipSummary($record);
            expect($summary)->toHaveKeys([
                'id',
                'period',
                'earnings',
                'deductions',
                'company_contributions',
                'net_income',
            ]);

            // Property: Summary earnings should contain all components
            expect($summary['earnings'])->toHaveKeys([
                'basic_salary',
                'fixed_allowances',
                'variable_allowances',
                'gross_income',
            ]);

            // Property: Summary deductions should contain all components
            expect($summary['deductions'])->toHaveKeys([
                'bpjs_kesehatan',
                'bpjs_jht',
                'bpjs_jp',
                'pph21',
                'other',
                'total',
            ]);

            // Property: Summary company contributions should contain all components
            expect($summary['company_contributions'])->toHaveKeys([
                'bpjs_kesehatan',
                'bpjs_jht',
                'bpjs_jp',
                'bpjs_jkk',
                'bpjs_jkm',
            ]);

            // Property: Summary values should match record values
            expect($summary['earnings']['basic_salary'])->toBe((float) $record->basic_salary);
            expect($summary['earnings']['fixed_allowances'])->toBe((float) $record->total_fixed_allowances);
            expect($summary['earnings']['variable_allowances'])->toBe((float) $record->total_variable_allowances);
            expect($summary['earnings']['gross_income'])->toBe((float) $record->gross_income);

            expect($summary['deductions']['bpjs_kesehatan'])->toBe((float) $record->bpjs_kesehatan_employee);
            expect($summary['deductions']['bpjs_jht'])->toBe((float) $record->bpjs_jht_employee);
            expect($summary['deductions']['bpjs_jp'])->toBe((float) $record->bpjs_jp_employee);
            expect($summary['deductions']['pph21'])->toBe((float) $record->pph21_amount);
            expect($summary['deductions']['total'])->toBe((float) $record->total_deductions);

            expect($summary['company_contributions']['bpjs_kesehatan'])->toBe((float) $record->bpjs_kesehatan_company);
            expect($summary['company_contributions']['bpjs_jht'])->toBe((float) $record->bpjs_jht_company);
            expect($summary['company_contributions']['bpjs_jp'])->toBe((float) $record->bpjs_jp_company);
            expect($summary['company_contributions']['bpjs_jkk'])->toBe((float) $record->bpjs_jkk_company);
            expect($summary['company_contributions']['bpjs_jkm'])->toBe((float) $record->bpjs_jkm_company);

            expect($summary['net_income'])->toBe((float) $record->net_income);
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
 * **Feature: hris-phase3-payroll, Property 18 Edge Cases: Payslip content with edge case values**
 *
 * Tests payslip content completeness with edge case values:
 * - Zero allowances
 * - Zero deductions
 * - Maximum salary values
 * - Minimum salary values
 * - Various BPJS ceiling scenarios
 *
 * **Validates: Requirements 5.2**
 */
test('Property 18 Edge Cases: Payslip content with edge case values', function () {
    $payrollService = app(PayrollCalculationService::class);
    $payslipService = app(PayslipService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Edge case 1: Minimum salary (no allowances)
    $batch1 = PayrunBatch::factory()->draft()->create();
    $employee1 = Employee::factory()->create();
    SalaryStructure::factory()->create([
        'employee_id' => $employee1->id,
        'effective_date' => $batch1->period_start_date,
        'basic_salary' => 1000000,
        'allowances_fixed' => [],
        'allowances_variable' => [],
    ]);

    $payrollService->calculatePayrun($batch1);
    $payrollService->approve($batch1, $approver);
    $payrollService->markAsPaid($batch1, $paidBy);
    $batch1->refresh();

    $record1 = $batch1->payrollRecords()->first();
    $record1->load(['employee.user', 'payrunBatch']);
    $html1 = $payslipService->generate($record1);

    // Should still contain all required sections
    expect($html1)->toContain('PENDAPATAN');
    expect($html1)->toContain('POTONGAN');
    expect($html1)->toContain('KONTRIBUSI PERUSAHAAN');
    expect($html1)->toContain('GAJI BERSIH');
    expect($html1)->toContain('NET INCOME');

    // Edge case 2: High salary (above BPJS ceilings)
    $batch2 = PayrunBatch::factory()->draft()->create();
    $employee2 = Employee::factory()->create();
    SalaryStructure::factory()->create([
        'employee_id' => $employee2->id,
        'effective_date' => $batch2->period_start_date,
        'basic_salary' => 50000000,
        'allowances_fixed' => ['position' => 5000000],
        'allowances_variable' => ['transport' => 2000000],
    ]);

    $payrollService->calculatePayrun($batch2);
    $payrollService->approve($batch2, $approver);
    $payrollService->markAsPaid($batch2, $paidBy);
    $batch2->refresh();

    $record2 = $batch2->payrollRecords()->first();
    $record2->load(['employee.user', 'payrunBatch']);
    $html2 = $payslipService->generate($record2);

    // Should contain all components even with high salary
    expect($html2)->toContain('PENDAPATAN');
    expect($html2)->toContain('POTONGAN');
    expect($html2)->toContain('KONTRIBUSI PERUSAHAAN');
    expect($html2)->toContain('GAJI BERSIH');

    // Summary should have all keys
    $summary2 = $payslipService->getPayslipSummary($record2);
    expect($summary2)->toHaveKeys([
        'id',
        'period',
        'earnings',
        'deductions',
        'company_contributions',
        'net_income',
    ]);

    // Edge case 3: Verify all numeric values are present and formatted
    $html3 = $payslipService->generate($record2);

    // All key amounts should be formatted with Indonesian number format
    expect($html3)->toContain('Rp');

    // Clean up
    $batch1->payrollRecords()->forceDelete();
    $batch1->forceDelete();
    $employee1->user->forceDelete();
    $employee1->forceDelete();

    $batch2->payrollRecords()->forceDelete();
    $batch2->forceDelete();
    $employee2->user->forceDelete();
    $employee2->forceDelete();

    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 18 Additional: Payslip content consistency**
 *
 * Tests that payslip content is consistent and deterministic:
 * - Multiple generations of same payslip produce identical HTML
 * - Summary data is consistent across retrievals
 * - All required fields are always present
 * - No missing or null values in critical fields
 *
 * **Validates: Requirements 5.2**
 */
test('Property 18 Additional: Payslip content consistency', function () {
    $payrollService = app(PayrollCalculationService::class);
    $payslipService = app(PayslipService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Run 50 iterations
    for ($i = 0; $i < 50; $i++) {
        $batch = PayrunBatch::factory()->draft()->create();
        $employee = Employee::factory()->create();

        SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $payrollService->calculatePayrun($batch);
        $payrollService->approve($batch, $approver);
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        $record = $batch->payrollRecords()->first();
        $record->load(['employee.user', 'payrunBatch']);

        // Property: Multiple HTML generations should be identical (deterministic)
        $html1 = $payslipService->generate($record);
        $html2 = $payslipService->generate($record);
        $html3 = $payslipService->generate($record);

        expect($html1)->toBe($html2);
        expect($html2)->toBe($html3);

        // Property: Multiple summary retrievals should be identical
        $summary1 = $payslipService->getPayslipSummary($record);
        $summary2 = $payslipService->getPayslipSummary($record);
        $summary3 = $payslipService->getPayslipSummary($record);

        expect($summary1)->toBe($summary2);
        expect($summary2)->toBe($summary3);

        // Property: All summary values should be numeric and non-null
        expect($summary1['earnings']['basic_salary'])->toBeNumeric();
        expect($summary1['earnings']['fixed_allowances'])->toBeNumeric();
        expect($summary1['earnings']['variable_allowances'])->toBeNumeric();
        expect($summary1['earnings']['gross_income'])->toBeNumeric();

        expect($summary1['deductions']['bpjs_kesehatan'])->toBeNumeric();
        expect($summary1['deductions']['bpjs_jht'])->toBeNumeric();
        expect($summary1['deductions']['bpjs_jp'])->toBeNumeric();
        expect($summary1['deductions']['pph21'])->toBeNumeric();
        expect($summary1['deductions']['other'])->toBeNumeric();
        expect($summary1['deductions']['total'])->toBeNumeric();

        expect($summary1['company_contributions']['bpjs_kesehatan'])->toBeNumeric();
        expect($summary1['company_contributions']['bpjs_jht'])->toBeNumeric();
        expect($summary1['company_contributions']['bpjs_jp'])->toBeNumeric();
        expect($summary1['company_contributions']['bpjs_jkk'])->toBeNumeric();
        expect($summary1['company_contributions']['bpjs_jkm'])->toBeNumeric();

        expect($summary1['net_income'])->toBeNumeric();

        // Property: All values should be >= 0
        expect($summary1['earnings']['basic_salary'])->toBeGreaterThanOrEqual(0);
        expect($summary1['earnings']['gross_income'])->toBeGreaterThanOrEqual(0);
        expect($summary1['deductions']['total'])->toBeGreaterThanOrEqual(0);
        expect($summary1['net_income'])->toBeGreaterThanOrEqual(0);

        // Clean up
        $batch->payrollRecords()->forceDelete();
        $batch->forceDelete();
        $employee->user->forceDelete();
        $employee->forceDelete();
    }

    // Clean up users
    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');
