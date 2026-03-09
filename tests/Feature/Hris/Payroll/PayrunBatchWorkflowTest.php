<?php

use App\Models\Hris\PayrunBatch;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\User;

describe('PayrunBatch status transitions', function () {
    it('starts in draft status', function () {
        $batch = PayrunBatch::factory()->draft()->create();

        expect($batch->status)->toBe(PayrunBatch::STATUS_DRAFT)
            ->and($batch->canCalculate())->toBeTrue()
            ->and($batch->isEditable())->toBeTrue()
            ->and($batch->isLocked())->toBeFalse();
    });

    it('allows calculation from draft', function () {
        $batch = PayrunBatch::factory()->draft()->create();

        expect($batch->canCalculate())->toBeTrue();
    });

    it('does not allow calculation from calculated', function () {
        $batch = PayrunBatch::factory()->calculated()->create();

        expect($batch->canCalculate())->toBeFalse();
    });

    it('allows submission from calculated', function () {
        $batch = PayrunBatch::factory()->calculated()->create();

        expect($batch->canSubmitForApproval())->toBeTrue();
    });

    it('does not allow submission from draft', function () {
        $batch = PayrunBatch::factory()->draft()->create();

        expect($batch->canSubmitForApproval())->toBeFalse();
    });

    it('allows finance approval from pending_finance', function () {
        $batch = PayrunBatch::factory()->pendingFinance()->create();

        expect($batch->canBeApprovedByFinance())->toBeTrue();
    });

    it('allows rejection from pending_finance', function () {
        $batch = PayrunBatch::factory()->pendingFinance()->create();

        expect($batch->canBeRejected())->toBeTrue();
    });

    it('allows resubmission from rejected', function () {
        $batch = PayrunBatch::factory()->rejected()->create();

        expect($batch->canBeResubmitted())->toBeTrue()
            ->and($batch->isEditable())->toBeTrue();
    });

    it('is locked when pending_finance', function () {
        $batch = PayrunBatch::factory()->pendingFinance()->create();

        expect($batch->isLocked())->toBeTrue()
            ->and($batch->isEditable())->toBeFalse();
    });

    it('is locked when paid', function () {
        $batch = PayrunBatch::factory()->paid()->create();

        expect($batch->isLocked())->toBeTrue()
            ->and($batch->isPaid())->toBeTrue();
    });

    it('does not allow any action on paid batch', function () {
        $batch = PayrunBatch::factory()->paid()->create();

        expect($batch->canCalculate())->toBeFalse()
            ->and($batch->canSubmitForApproval())->toBeFalse()
            ->and($batch->canBeApprovedByFinance())->toBeFalse()
            ->and($batch->canBeRejected())->toBeFalse()
            ->and($batch->canBeResubmitted())->toBeFalse();
    });
});

describe('PayrunBatch relationships and accessors', function () {
    it('has payroll records relationship', function () {
        $batch = PayrunBatch::factory()->create();
        $employee = Employee::factory()->create();

        PayrollRecord::create([
            'payrun_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'gross_income' => 5000000,
            'taxable_income' => 5000000,
            'total_deductions' => 0,
            'net_income' => 5000000,
        ]);

        expect($batch->payrollRecords)->toHaveCount(1);
    });

    it('calculates employee count', function () {
        $batch = PayrunBatch::factory()->create();

        foreach (range(1, 3) as $_) {
            $employee = Employee::factory()->create();
            PayrollRecord::create([
                'payrun_batch_id' => $batch->id,
                'employee_id' => $employee->id,
                'basic_salary' => 5000000,
                'gross_income' => 5000000,
                'taxable_income' => 5000000,
                'total_deductions' => 0,
                'net_income' => 5000000,
            ]);
        }

        expect($batch->employee_count)->toBe(3);
    });

    it('calculates total net income', function () {
        $batch = PayrunBatch::factory()->create();

        foreach ([5000000, 6000000] as $salary) {
            $employee = Employee::factory()->create();
            PayrollRecord::create([
                'payrun_batch_id' => $batch->id,
                'employee_id' => $employee->id,
                'basic_salary' => $salary,
                'gross_income' => $salary,
                'taxable_income' => $salary,
                'total_deductions' => 0,
                'net_income' => $salary,
            ]);
        }

        expect($batch->total_net_income)->toBe(11000000.0);
    });

    it('calculates total gross income', function () {
        $batch = PayrunBatch::factory()->create();

        foreach ([5000000, 7000000] as $salary) {
            $employee = Employee::factory()->create();
            PayrollRecord::create([
                'payrun_batch_id' => $batch->id,
                'employee_id' => $employee->id,
                'basic_salary' => $salary,
                'gross_income' => $salary,
                'taxable_income' => $salary,
                'total_deductions' => 0,
                'net_income' => $salary,
            ]);
        }

        expect($batch->total_gross_income)->toBe(12000000.0);
    });

    it('returns period label', function () {
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        expect($batch->period_label)->toContain('Oct')
            ->and($batch->period_label)->toContain('2025');
    });
});

describe('PayrunBatch scopes', function () {
    it('filters by draft status', function () {
        PayrunBatch::factory()->draft()->create();
        PayrunBatch::factory()->paid()->create();

        expect(PayrunBatch::draft()->count())->toBe(1);
    });

    it('filters by calculated status', function () {
        PayrunBatch::factory()->calculated()->create();
        PayrunBatch::factory()->draft()->create();

        expect(PayrunBatch::calculated()->count())->toBe(1);
    });

    it('filters by paid status', function () {
        PayrunBatch::factory()->paid()->create();
        PayrunBatch::factory()->draft()->create();

        expect(PayrunBatch::paid()->count())->toBe(1);
    });
});
