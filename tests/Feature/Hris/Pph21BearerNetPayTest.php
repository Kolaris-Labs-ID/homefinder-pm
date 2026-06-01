<?php

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * One employee, basic salary 10M, no allowances/deductions, BPJS + prorate off,
 * so net pay isolates the PPh 21 bearer policy. TER rate at 10M = 2% => 200,000.
 */
function makePph21NetPayRecord(string $method): PayrollRecord
{
    $batch = PayrunBatch::factory()->draft()->create();
    $employee = Employee::factory()->create();
    SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'effective_date' => $batch->period_start_date,
        'basic_salary' => 10000000,
        'allowances_fixed' => [],
        'allowances_variable' => [],
        'deductions' => [],
        'daily_allowance_rate' => 0,
        'weekly_allowance_enabled' => false,
        'prorate_enabled' => false,
        'bpjs_enabled' => false,
        'pph21_method' => $method,
    ]);

    return app(PayrollCalculationService::class)->calculateEmployee($employee, $batch);
}

describe('PPh21 net pay per method (10M base @ 2% TER, BPJS off)', function () {
    it('gross: employee bears the tax (net = gross - 200k)', function () {
        $r = makePph21NetPayRecord('gross');

        expect((float) $r->gross_income)->toBe(10000000.0)
            ->and((float) $r->pph21_amount)->toBe(200000.0)
            ->and((float) $r->net_income)->toBe(9800000.0);
    });

    it('netto: company bears the tax (nothing withheld; take-home = full gross)', function () {
        $r = makePph21NetPayRecord('netto');

        expect((float) $r->gross_income)->toBe(10000000.0)
            ->and((float) $r->pph21_amount)->toBe(0.0)
            ->and((float) $r->net_income)->toBe(10000000.0)
            ->and((float) ($r->details['pph21_company_borne'] ?? 0))->toBe(200000.0);
    });

    it('gross_up: taxable tunjangan pajak preserves take-home', function () {
        $r = makePph21NetPayRecord('gross_up');

        // tunjangan = 200000 / (1 - 0.02) = 204,081.63, added to gross then withheld
        expect((float) $r->gross_income)->toEqualWithDelta(10204081.63, 0.01)
            ->and((float) $r->pph21_amount)->toEqualWithDelta(204081.63, 0.01)
            ->and((float) $r->net_income)->toEqualWithDelta(10000000.0, 0.01)
            ->and((float) ($r->details['tunjangan_pajak'] ?? 0))->toEqualWithDelta(204081.63, 0.01);
    });

    it('employer-borne methods raise take-home vs gross by exactly the tax', function () {
        $gross = makePph21NetPayRecord('gross');
        $netto = makePph21NetPayRecord('netto');
        $grossUp = makePph21NetPayRecord('gross_up');

        expect((float) $netto->net_income)->toBe((float) $gross->net_income + 200000.0)
            ->and((float) $grossUp->net_income)->toEqualWithDelta((float) $netto->net_income, 0.01);
    });
});
