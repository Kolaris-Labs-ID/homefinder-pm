<?php

use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\Employee;

describe('PayrollRecord model attributes', function () {
    it('calculates total BPJS employee contributions', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'bpjs_kesehatan_employee' => 50000,
            'bpjs_jht_employee' => 100000,
            'bpjs_jp_employee' => 50000,
            'total_deductions' => 350000,
            'net_income' => 4650000,
        ]);

        expect($record->total_bpjs_employee)->toBe(200000.0);
    });

    it('calculates total BPJS company contributions', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'bpjs_kesehatan_company' => 200000,
            'bpjs_jht_company' => 185000,
            'bpjs_jp_company' => 100000,
            'bpjs_jkk_company' => 12000,
            'bpjs_jkm_company' => 15000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect($record->total_bpjs_company)->toBe(512000.0);
    });

    it('returns prorate label', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
            'total_working_days' => 21,
            'actual_days_worked' => 15,
        ]);

        expect($record->prorate_label)->toBe('15/21 HK');
    });

    it('returns dash for prorate label when no data', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect($record->prorate_label)->toBe('-');
    });

    it('calculates prorate percentage', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
            'prorate_ratio' => 0.7143,
        ]);

        expect($record->prorate_percentage)->toBeGreaterThan(71)
            ->and($record->prorate_percentage)->toBeLessThan(72);
    });

    it('detects prorated records', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create();

        $prorated = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
            'prorate_ratio' => 0.75,
        ]);

        $full = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => Employee::factory()->create()->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
            'prorate_ratio' => 1.0,
        ]);

        expect($prorated->isProrated())->toBeTrue()
            ->and($full->isProrated())->toBeFalse();
    });
});

describe('PayrollRecord editability', function () {
    it('is editable when batch is draft', function () {
        $batch = PayrunBatch::factory()->draft()->create();
        $employee = Employee::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect($record->isEditable())->toBeTrue();
    });

    it('is not editable when batch is locked', function () {
        $batch = PayrunBatch::factory()->pendingFinance()->create();
        $employee = Employee::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect($record->isEditable())->toBeFalse();
    });

    it('prevents update when batch is locked', function () {
        $batch = PayrunBatch::factory()->paid()->create();
        $employee = Employee::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect(fn () => $record->update(['basic_salary' => 6000000]))
            ->toThrow(\Exception::class, 'Cannot modify payroll record');
    });

    it('prevents deletion when batch is locked', function () {
        $batch = PayrunBatch::factory()->paid()->create();
        $employee = Employee::factory()->create();

        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect(fn () => $record->delete())
            ->toThrow(\Exception::class, 'Cannot delete payroll record');
    });
});

describe('PayrollRecord scopes', function () {
    it('filters by employee', function () {
        $batch = PayrunBatch::factory()->create();
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee1->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee2->id,
            'basic_salary' => 6000000,
            'gross_income' => 6000000,
            'taxable_income' => 6000000,
            'total_deductions' => 0,
            'net_income' => 6000000,
        ]);

        expect(PayrollRecord::forEmployee($employee1->id)->count())->toBe(1);
    });

    it('filters by batch', function () {
        $batch1 = PayrunBatch::factory()->create();
        $batch2 = PayrunBatch::factory()->create();
        $employee = Employee::factory()->create();

        PayrollRecord::create([
            'payrun_batch_id' => $batch1->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        PayrollRecord::create([
            'payrun_batch_id' => $batch2->id,
            'employee_id' => $employee->id,
            'basic_salary' => 6000000,
            'gross_income' => 6000000,
            'taxable_income' => 6000000,
            'total_deductions' => 0,
            'net_income' => 6000000,
        ]);

        expect(PayrollRecord::forBatch($batch1->id)->count())->toBe(1);
    });
});
