<?php

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper function to create a valid payroll record with required fields.
 */
function createPayrollRecord(array $overrides = []): PayrollRecord
{
    $employee = Employee::factory()->create();
    $batch = PayrunBatch::factory()->create();

    $defaults = [
        'payrun_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'basic_salary' => 4000000,
        'total_fixed_allowances' => 0,
        'total_variable_allowances' => 0,
        'gross_income' => 4000000,
        'taxable_income' => 3800000,
        'bpjs_kesehatan_employee' => 40000,
        'bpjs_kesehatan_company' => 160000,
        'bpjs_jht_employee' => 80000,
        'bpjs_jht_company' => 148000,
        'bpjs_jp_employee' => 40000,
        'bpjs_jp_company' => 80000,
        'bpjs_jkk_company' => 21600,
        'bpjs_jkm_company' => 12000,
        'pph21_amount' => 0,
        'other_deductions' => 0,
        'total_deductions' => 160000,
        'net_income' => 3840000,
    ];

    return PayrollRecord::create(array_merge($defaults, $overrides));
}

describe('PayrollRecord Model New Fields', function () {
    it('has prorate tracking fields', function () {
        $record = createPayrollRecord([
            'basic_salary' => 2857143,
            'gross_income' => 3957143,
            'net_income' => 3500000,
            'total_working_days' => 21,
            'actual_days_worked' => 15,
            'prorate_ratio' => 0.7143,
            'full_basic_salary' => 4000000,
            'prorated_basic_salary' => 2857143,
        ]);

        expect($record->total_working_days)->toBe(21)
            ->and($record->actual_days_worked)->toBe(15)
            ->and((float) $record->prorate_ratio)->toBeGreaterThan(0.71)->toBeLessThan(0.72)
            ->and((float) $record->full_basic_salary)->toBe(4000000.00)
            ->and((float) $record->prorated_basic_salary)->toBe(2857143.00);
    });

    it('has daily allowance total field', function () {
        $record = createPayrollRecord([
            'basic_salary' => 4000000,
            'gross_income' => 5050000,
            'net_income' => 4500000,
            'daily_allowance_total' => 1050000,
        ]);

        expect((float) $record->daily_allowance_total)->toBe(1050000.00);
    });

    it('has bpjs_company_paid field', function () {
        $record = createPayrollRecord([
            'bpjs_company_paid' => true,
        ]);

        expect($record->bpjs_company_paid)->toBeTrue();
    });

    it('casts bpjs_company_paid to boolean', function () {
        $record = createPayrollRecord([
            'bpjs_company_paid' => 1,
        ]);

        expect($record->bpjs_company_paid)->toBe(true);
    });
});

describe('PayrollRecord Prorate Helpers', function () {
    it('returns prorate label', function () {
        $record = createPayrollRecord([
            'total_working_days' => 21,
            'actual_days_worked' => 15,
        ]);

        expect($record->prorate_label)->toBe('15/21 HK');
    });

    it('returns dash for prorate label when no data', function () {
        $record = createPayrollRecord([
            'total_working_days' => null,
            'actual_days_worked' => null,
        ]);

        expect($record->prorate_label)->toBe('-');
    });

    it('calculates prorate percentage', function () {
        $record = createPayrollRecord([
            'prorate_ratio' => 0.7143,
        ]);

        expect($record->prorate_percentage)->toBeGreaterThan(71)
            ->and($record->prorate_percentage)->toBeLessThan(72);
    });

    it('returns 100% when prorate ratio is null', function () {
        $record = createPayrollRecord([
            'prorate_ratio' => null,
        ]);

        expect($record->prorate_percentage)->toBe(100.0);
    });
});

describe('PayrollRecord isProrated', function () {
    it('returns true when prorate ratio is less than 1', function () {
        $record = createPayrollRecord([
            'prorate_ratio' => 0.7143,
        ]);

        expect($record->isProrated())->toBeTrue();
    });

    it('returns false when prorate ratio is 1 (full month)', function () {
        $record = createPayrollRecord([
            'prorate_ratio' => 1.0,
        ]);

        expect($record->isProrated())->toBeFalse();
    });

    it('returns false when prorate ratio is null', function () {
        $record = createPayrollRecord([
            'prorate_ratio' => null,
        ]);

        expect($record->isProrated())->toBeFalse();
    });
});
