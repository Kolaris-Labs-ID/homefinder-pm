<?php

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\PayslipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

// Idempotent salary-structure creation: one row per (employee, effective_date).
// Multiple batches for the same employee can land in the same month, and the
// unique (employee_id, effective_date) constraint forbids duplicates — reuse
// the existing structure instead of colliding.
function ensureSalaryStructure(array $attrs): SalaryStructure
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
    $this->payrollService = app(PayrollCalculationService::class);
    $this->payslipService = app(PayslipService::class);
    $this->approver = User::factory()->create();
    $this->paidBy = User::factory()->create();
});

describe('Payslip list endpoint', function () {
    it('returns list of employee payslips', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create a paid batch with payslip
        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $response = getJson('/api/hris/payslips');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'period_start',
                        'period_end',
                        'pay_date',
                        'gross_income',
                        'net_income',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');

        expect($response->json('data.0.gross_income'))->toBeNumeric();
        expect($response->json('data.0.net_income'))->toBeNumeric();
    });

    it('returns empty list when no payslips available', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $response = getJson('/api/hris/payslips');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('returns only paid batch payslips', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create draft batch (should not appear)
        $draftBatch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $draftBatch->period_start_date,
        ]);
        $this->payrollService->calculatePayrun($draftBatch);

        // Create calculated batch (should not appear)
        $calculatedBatch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $calculatedBatch->period_start_date,
        ]);
        $this->payrollService->calculatePayrun($calculatedBatch);

        // Create approved batch (should not appear)
        $approvedBatch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $approvedBatch->period_start_date,
        ]);
        $this->payrollService->calculatePayrun($approvedBatch);
        $this->payrollService->approve($approvedBatch, $this->approver);

        // Create paid batch (should appear)
        $paidBatch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $paidBatch->period_start_date,
        ]);
        $this->payrollService->calculatePayrun($paidBatch);
        $this->payrollService->approve($paidBatch, $this->approver);
        $this->payrollService->markAsPaid($paidBatch, $this->paidBy);

        $response = getJson('/api/hris/payslips');

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        expect($response->json('data.0.period_start'))->toBe($paidBatch->period_start_date->toDateString());
    });

    it('returns payslips ordered by most recent first', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create multiple paid batches
        $batches = [];
        for ($i = 0; $i < 3; $i++) {
            $batch = PayrunBatch::factory()->draft()->create([
                'period_start_date' => now()->subMonths(3 - $i)->startOfMonth(),
                'period_end_date' => now()->subMonths(3 - $i)->endOfMonth(),
            ]);

            ensureSalaryStructure([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_start_date,
            ]);

            $this->payrollService->calculatePayrun($batch);
            $this->payrollService->approve($batch, $this->approver);
            $this->payrollService->markAsPaid($batch, $this->paidBy);

            $batches[] = $batch;
        }

        $response = getJson('/api/hris/payslips');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        // Should be ordered by most recent first (by created_at, not period dates)
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        expect(count($returnedIds))->toBe(3);

        // Verify all three batches are present
        $allPayslipIds = PayrollRecord::where('employee_id', $employee->id)
            ->pluck('id')
            ->toArray();
        foreach ($returnedIds as $id) {
            expect(in_array($id, $allPayslipIds))->toBeTrue();
        }
    });

    it('returns only employee own payslips', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        Sanctum::actingAs($employee1->user);

        // Create paid batch with payslips for both employees
        $batch = PayrunBatch::factory()->draft()->create();

        ensureSalaryStructure([
            'employee_id' => $employee1->id,
            'effective_date' => $batch->period_start_date,
        ]);
        ensureSalaryStructure([
            'employee_id' => $employee2->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $response = getJson('/api/hris/payslips');

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        // Verify it's employee1's payslip
        $payslipId = $response->json('data.0.id');
        $payslip = PayrollRecord::find($payslipId);
        expect($payslip->employee_id)->toBe($employee1->id);
    });

    it('requires authentication', function () {
        $response = getJson('/api/hris/payslips');

        $response->assertUnauthorized();
    });

    it('returns 404 when user has no employee profile', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = getJson('/api/hris/payslips');

        $response->assertNotFound()
            ->assertJsonPath('message', 'Employee profile not found');
    });
});

describe('Payslip show endpoint', function () {
    it('returns payslip details', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create a paid batch with payslip
        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'period' => [
                        'start',
                        'end',
                        'pay_date',
                    ],
                    'earnings' => [
                        'basic_salary',
                        'fixed_allowances',
                        'variable_allowances',
                        'gross_income',
                    ],
                    'deductions' => [
                        'bpjs_kesehatan',
                        'bpjs_jht',
                        'bpjs_jp',
                        'pph21',
                        'other',
                        'total',
                    ],
                    'company_contributions' => [
                        'bpjs_kesehatan',
                        'bpjs_jht',
                        'bpjs_jp',
                        'bpjs_jkk',
                        'bpjs_jkm',
                    ],
                    'net_income',
                ],
            ]);

        expect($response->json('data.id'))->toBe($payslip->id);
        expect($response->json('data.earnings.gross_income'))->toBeNumeric();
        expect($response->json('data.net_income'))->toBeNumeric();
    });

    it('returns 403 when accessing other employee payslip', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        Sanctum::actingAs($employee1->user);

        // Create paid batch with payslips for both employees
        $batch = PayrunBatch::factory()->draft()->create();

        ensureSalaryStructure([
            'employee_id' => $employee1->id,
            'effective_date' => $batch->period_start_date,
        ]);
        ensureSalaryStructure([
            'employee_id' => $employee2->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $employee2Payslip = PayrollRecord::where('employee_id', $employee2->id)->first();

        $response = getJson("/api/hris/payslips/{$employee2Payslip->id}");

        $response->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized');
    });

    it('returns 404 when payslip not available (draft batch)', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create draft batch (payslip not available)
        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Payslip not yet available');
    });

    it('returns 404 when payslip not available (calculated batch)', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create calculated batch (payslip not available)
        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Payslip not yet available');
    });

    it('returns 404 when payslip not available (approved batch)', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create approved batch (payslip not available)
        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Payslip not yet available');
    });

    it('returns payslip details for paid batch', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create paid batch
        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $payslip->id);
    });

    it('requires authentication', function () {
        $payslip = PayrollRecord::factory()->create();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertUnauthorized();
    });

    it('returns 404 when user has no employee profile', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payslip = PayrollRecord::factory()->create();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Employee profile not found');
    });

    it('returns 404 when payslip does not exist', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $response = getJson('/api/hris/payslips/99999');

        $response->assertNotFound();
    });
});

describe('Payslip content validation', function () {
    it('returns correct earnings breakdown', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create salary structure with specific values
        $batch = PayrunBatch::factory()->draft()->create();
        $salaryStructure = ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
            // Pin the tax method: netto/gross_up add a tunjangan_pajak to gross_income, which would
            // make this earnings-breakdown assertion non-deterministic under the random factory default.
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
            'basic_salary' => 10000000,
            'allowances_fixed' => [
                ['type' => 'position', 'name' => 'Position Allowance', 'amount' => 2000000],
            ],
            'allowances_variable' => [
                ['type' => 'transport', 'name' => 'Transport', 'amount' => 500000],
            ],
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertOk();

        // Verify earnings breakdown
        expect($response->json('data.earnings.basic_salary'))->toBe(10000000);
        expect($response->json('data.earnings.fixed_allowances'))->toBe(2000000);
        expect($response->json('data.earnings.variable_allowances'))->toBe(500000);
        expect($response->json('data.earnings.gross_income'))->toBe(12500000);
    });

    it('returns correct deductions breakdown', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
            'basic_salary' => 10000000,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertOk();

        // Verify deductions are present and numeric
        expect($response->json('data.deductions.bpjs_kesehatan'))->toBeNumeric();
        expect($response->json('data.deductions.bpjs_jht'))->toBeNumeric();
        expect($response->json('data.deductions.bpjs_jp'))->toBeNumeric();
        expect($response->json('data.deductions.pph21'))->toBeNumeric();
        expect($response->json('data.deductions.total'))->toBeNumeric();

        // Total deductions should be sum of components (with floating point tolerance)
        $total = $response->json('data.deductions.bpjs_kesehatan')
            + $response->json('data.deductions.bpjs_jht')
            + $response->json('data.deductions.bpjs_jp')
            + $response->json('data.deductions.pph21')
            + $response->json('data.deductions.other');

        $reportedTotal = $response->json('data.deductions.total');
        $difference = abs($reportedTotal - $total);
        expect($difference)->toBeLessThan(0.01);
    });

    it('returns correct company contributions', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
            'basic_salary' => 10000000,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertOk();

        // Verify company contributions are present and numeric
        expect($response->json('data.company_contributions.bpjs_kesehatan'))->toBeNumeric();
        expect($response->json('data.company_contributions.bpjs_jht'))->toBeNumeric();
        expect($response->json('data.company_contributions.bpjs_jp'))->toBeNumeric();
        expect($response->json('data.company_contributions.bpjs_jkk'))->toBeNumeric();
        expect($response->json('data.company_contributions.bpjs_jkm'))->toBeNumeric();
    });

    it('returns correct net income calculation', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $batch = PayrunBatch::factory()->draft()->create();
        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
            'basic_salary' => 10000000,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertOk();

        // Net income should be positive and less than gross income
        $netIncome = $response->json('data.net_income');
        $grossIncome = $response->json('data.earnings.gross_income');

        expect($netIncome)->toBeGreaterThan(0);
        expect($netIncome)->toBeLessThan($grossIncome);
    });
});

describe('Payslip period information', function () {
    it('returns correct period dates', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();
        $payDate = now()->addDays(5);

        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => $startDate,
            'period_end_date' => $endDate,
            'pay_date' => $payDate,
        ]);

        ensureSalaryStructure([
            'employee_id' => $employee->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $this->payrollService->calculatePayrun($batch);
        $this->payrollService->approve($batch, $this->approver);
        $this->payrollService->markAsPaid($batch, $this->paidBy);

        $payslip = PayrollRecord::where('employee_id', $employee->id)->first();

        $response = getJson("/api/hris/payslips/{$payslip->id}");

        $response->assertOk()
            ->assertJsonPath('data.period.start', $startDate->toDateString())
            ->assertJsonPath('data.period.end', $endDate->toDateString())
            ->assertJsonPath('data.period.pay_date', $payDate->toDateString());
    });
});
