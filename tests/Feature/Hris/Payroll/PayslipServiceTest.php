<?php

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Services\Hris\PayslipService;

beforeEach(function () {
    $this->service = new PayslipService();
});

function createPaidBatchWithRecord(array $recordOverrides = []): array
{
    $employee = Employee::factory()->create([
        'nik' => '1234567890123456',
        'date_of_birth' => '1990-05-15',
    ]);

    $batch = PayrunBatch::factory()->create([
        'status' => PayrunBatch::STATUS_PAID,
        'paid_at' => now(),
        'period_start_date' => '2025-10-01',
        'period_end_date' => '2025-10-31',
        'pay_date' => '2025-11-05',
    ]);

    $record = PayrollRecord::create(array_merge([
        'payrun_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'basic_salary' => 5000000,
        'total_fixed_allowances' => 500000,
        'total_variable_allowances' => 300000,
        'gross_income' => 5800000,
        'taxable_income' => 5500000,
        'bpjs_kesehatan_employee' => 50000,
        'bpjs_kesehatan_company' => 200000,
        'bpjs_jht_employee' => 100000,
        'bpjs_jht_company' => 185000,
        'bpjs_jp_employee' => 50000,
        'bpjs_jp_company' => 100000,
        'bpjs_jkk_company' => 12000,
        'bpjs_jkm_company' => 15000,
        'pph21_amount' => 150000,
        'other_deductions' => 0,
        'total_deductions' => 350000,
        'net_income' => 5450000,
        'details' => [],
    ], $recordOverrides));

    return compact('employee', 'batch', 'record');
}

describe('PayslipService isPayslipAvailable', function () {
    it('returns true for paid batch', function () {
        ['record' => $record] = createPaidBatchWithRecord();

        expect($this->service->isPayslipAvailable($record))->toBeTrue();
    });

    it('returns false for draft batch', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create(['status' => PayrunBatch::STATUS_DRAFT]);
        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect($this->service->isPayslipAvailable($record))->toBeFalse();
    });

    it('returns false for calculated batch', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create(['status' => PayrunBatch::STATUS_CALCULATED]);
        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect($this->service->isPayslipAvailable($record))->toBeFalse();
    });

    it('returns false for pending_finance batch', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create(['status' => PayrunBatch::STATUS_PENDING_FINANCE]);
        $record = PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect($this->service->isPayslipAvailable($record))->toBeFalse();
    });
});

describe('PayslipService getEmployeePayslips', function () {
    it('returns only payslips from paid batches', function () {
        $employee = Employee::factory()->create();

        $paidBatch = PayrunBatch::factory()->create(['status' => PayrunBatch::STATUS_PAID]);
        $draftBatch = PayrunBatch::factory()->create(['status' => PayrunBatch::STATUS_DRAFT]);

        PayrollRecord::create([
            'payrun_batch_id' => $paidBatch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        PayrollRecord::create([
            'payrun_batch_id' => $draftBatch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        $payslips = $this->service->getEmployeePayslips($employee);

        expect($payslips)->toHaveCount(1)
            ->and($payslips->first()->payrunBatch->status)->toBe(PayrunBatch::STATUS_PAID);
    });

    it('returns empty collection when no paid batches exist', function () {
        $employee = Employee::factory()->create();

        $payslips = $this->service->getEmployeePayslips($employee);

        expect($payslips)->toHaveCount(0);
    });

    it('does not return other employees payslips', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        $batch = PayrunBatch::factory()->create(['status' => PayrunBatch::STATUS_PAID]);

        PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee1->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        $payslips = $this->service->getEmployeePayslips($employee2);

        expect($payslips)->toHaveCount(0);
    });
});

describe('PayslipService getPayslipSummary', function () {
    it('returns correct summary structure', function () {
        ['record' => $record] = createPaidBatchWithRecord();
        $record->load(['payrunBatch']);

        $summary = $this->service->getPayslipSummary($record);

        expect($summary)->toHaveKeys(['id', 'period', 'earnings', 'deductions', 'company_contributions', 'net_income'])
            ->and($summary['period'])->toHaveKeys(['start', 'end', 'pay_date'])
            ->and($summary['earnings'])->toHaveKeys(['basic_salary', 'fixed_allowances', 'variable_allowances', 'gross_income'])
            ->and($summary['deductions'])->toHaveKeys(['bpjs_kesehatan', 'bpjs_jht', 'bpjs_jp', 'pph21', 'other', 'total'])
            ->and($summary['company_contributions'])->toHaveKeys(['bpjs_kesehatan', 'bpjs_jht', 'bpjs_jp', 'bpjs_jkk', 'bpjs_jkm']);
    });

    it('returns correct numeric values', function () {
        ['record' => $record] = createPaidBatchWithRecord();
        $record->load(['payrunBatch']);

        $summary = $this->service->getPayslipSummary($record);

        expect($summary['earnings']['basic_salary'])->toBe(5000000.0)
            ->and($summary['earnings']['gross_income'])->toBe(5800000.0)
            ->and($summary['net_income'])->toBe(5450000.0)
            ->and($summary['deductions']['pph21'])->toBe(150000.0);
    });

    it('returns correct period dates', function () {
        ['record' => $record] = createPaidBatchWithRecord();
        $record->load(['payrunBatch']);

        $summary = $this->service->getPayslipSummary($record);

        expect($summary['period']['start'])->toBe('2025-10-01')
            ->and($summary['period']['end'])->toBe('2025-10-31')
            ->and($summary['period']['pay_date'])->toBe('2025-11-05');
    });
});
