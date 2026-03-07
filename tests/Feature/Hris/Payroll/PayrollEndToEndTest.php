<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\AttendancePayrollService;
use App\Services\Hris\BpjsCalculationService;
use App\Services\Hris\PayrollApprovalService;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\PayrollSettingService;
use App\Services\Hris\PayslipService;
use App\Services\Hris\Pph21CalculationService;
use Carbon\Carbon;

beforeEach(function () {
    PayrollApprovalLog::query()->forceDelete();
    PayrollWorkflowStep::query()->delete();
    PayrollWorkflowConfig::query()->delete();

    $this->settingService = new PayrollSettingService();
    $this->calcService = new PayrollCalculationService(
        new BpjsCalculationService($this->settingService),
        new Pph21CalculationService($this->settingService),
        new AttendancePayrollService($this->settingService),
        $this->settingService
    );
    $this->approvalService = new PayrollApprovalService();
    $this->payslipService = new PayslipService();
});

describe('End-to-end monthly payroll flow', function () {
    it('completes full cycle: create → calculate → submit → approve → payslip', function () {
        // Setup workflow
        $financeUser = User::factory()->create();
        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser->id,
            'order' => 1,
        ]);

        // Create employee with salary
        $employee = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-01-01',
            'basic_salary' => 6000000,
            'allowances_fixed' => [['name' => 'Transport', 'amount' => 300000]],
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Create attendance (full month)
        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 23) {
            if (!$date->isWeekend()) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'date' => $date->copy(),
                    'status' => AttendanceLog::STATUS_PRESENT,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'source' => AttendanceLog::SOURCE_WEB,
                ]);
                $daysCreated++;
            }
            $date->addDay();
        }

        // Step 1: Create batch
        $batch = PayrunBatch::create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
            'pay_date' => '2025-11-05',
            'status' => PayrunBatch::STATUS_DRAFT,
        ]);

        // Step 2: Calculate
        $results = $this->calcService->calculatePayrun($batch);
        expect($results['processed'])->toBeGreaterThanOrEqual(1);

        $batch->refresh();
        expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);

        // Verify payroll record
        $record = PayrollRecord::forEmployee($employee->id)->forBatch($batch->id)->first();
        expect($record)->not->toBeNull()
            ->and((float) $record->basic_salary)->toBe(6000000.0)
            ->and((float) $record->gross_income)->toBeGreaterThan(6000000)
            ->and((float) $record->net_income)->toBeGreaterThan(0)
            ->and((float) $record->net_income)->toBeLessThan((float) $record->gross_income);

        // Step 3: Submit for approval
        $submitter = User::factory()->create();
        $this->approvalService->submitForApproval($batch, $submitter);
        $batch->refresh();
        expect($batch->status)->toBe('pending_finance');

        // Step 4: Finance approves and pays
        $this->approvalService->approveAndPay($batch, $financeUser);
        $batch->refresh();
        expect($batch->status)->toBe('paid');

        // Step 5: Verify payslip availability
        $record->refresh();
        expect($this->payslipService->isPayslipAvailable($record))->toBeTrue();

        // Step 6: Verify payslip summary
        $summary = $this->payslipService->getPayslipSummary($record);
        expect($summary['earnings']['basic_salary'])->toBe(6000000.0)
            ->and($summary['net_income'])->toBeGreaterThan(0);

        // Step 7: Verify employee can see payslip
        $payslips = $this->payslipService->getEmployeePayslips($employee);
        expect($payslips)->toHaveCount(1);

        // Verify audit trail
        $logs = PayrollApprovalLog::where('batch_id', $batch->id)->orderBy('id')->get();
        expect($logs)->toHaveCount(2);
    });

    it('handles rejection and resubmission in full flow', function () {
        $financeUser = User::factory()->create();
        $submitter = User::factory()->create();
        $config = PayrollWorkflowConfig::create([
            'module' => PayrollWorkflowConfig::MODULE_MONTHLY,
            'is_active' => true,
        ]);
        PayrollWorkflowStep::create([
            'payroll_workflow_config_id' => $config->id,
            'step_type' => PayrollWorkflowStep::STEP_FINANCE,
            'assignee_type' => 'user',
            'assignee_user_id' => $financeUser->id,
            'order' => 1,
        ]);

        $employee = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-01-01',
            'basic_salary' => 5000000,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 23) {
            if (!$date->isWeekend()) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'date' => $date->copy(),
                    'status' => AttendanceLog::STATUS_PRESENT,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'source' => AttendanceLog::SOURCE_WEB,
                ]);
                $daysCreated++;
            }
            $date->addDay();
        }

        $batch = PayrunBatch::create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
            'pay_date' => '2025-11-05',
            'status' => PayrunBatch::STATUS_DRAFT,
        ]);

        // Calculate → Submit → Reject → Resubmit → Approve
        $this->calcService->calculatePayrun($batch);
        $this->approvalService->submitForApproval($batch, $submitter);
        $this->approvalService->reject($batch, $financeUser, 'Amounts need review');

        $batch->refresh();
        expect($batch->status)->toBe('rejected')
            ->and($batch->rejection_reason)->toBe('Amounts need review');

        // Payslip should NOT be available during rejection
        $record = PayrollRecord::forEmployee($employee->id)->forBatch($batch->id)->first();
        $record->load('payrunBatch');
        expect($this->payslipService->isPayslipAvailable($record))->toBeFalse();

        // Resubmit and approve
        $this->approvalService->resubmit($batch, $submitter);
        $this->approvalService->approveAndPay($batch, $financeUser);

        $batch->refresh();
        expect($batch->status)->toBe('paid');

        // Now payslip should be available (reload relationship)
        $record->load('payrunBatch');
        expect($this->payslipService->isPayslipAvailable($record))->toBeTrue();
    });
});

describe('Payroll data integrity', function () {
    it('gross income equals basic + fixed + variable + daily allowances', function () {
        $employee = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-01-01',
            'basic_salary' => 5000000,
            'allowances_fixed' => [['name' => 'Transport', 'amount' => 300000]],
            'allowances_variable' => [
                ['name' => 'Uang Makan', 'type' => 'attendance_based', 'per_day_amount' => 50000],
            ],
            'daily_allowance_rate' => 70000,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 20) {
            if (!$date->isWeekend()) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'date' => $date->copy(),
                    'status' => AttendanceLog::STATUS_PRESENT,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'source' => AttendanceLog::SOURCE_WEB,
                ]);
                $daysCreated++;
            }
            $date->addDay();
        }

        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $record = $this->calcService->calculateEmployee($employee, $batch);

        $expectedGross = (float) $record->basic_salary
            + (float) $record->total_fixed_allowances
            + (float) $record->total_variable_allowances
            + (float) $record->daily_allowance_total;

        expect((float) $record->gross_income)->toBe($expectedGross);
    });

    it('total deductions equals BPJS employee + PPh21 + other deductions', function () {
        $employee = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-01-01',
            'basic_salary' => 8000000,
            'deductions' => [['name' => 'Loan', 'amount' => 500000]],
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 23) {
            if (!$date->isWeekend()) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'date' => $date->copy(),
                    'status' => AttendanceLog::STATUS_PRESENT,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'source' => AttendanceLog::SOURCE_WEB,
                ]);
                $daysCreated++;
            }
            $date->addDay();
        }

        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $record = $this->calcService->calculateEmployee($employee, $batch);

        $expectedDeductions = (float) $record->bpjs_kesehatan_employee
            + (float) $record->bpjs_jht_employee
            + (float) $record->bpjs_jp_employee
            + (float) $record->pph21_amount
            + (float) $record->other_deductions;

        expect((float) $record->total_deductions)->toBe(round($expectedDeductions, 2));
    });

    it('payroll record stores details as JSON', function () {
        $employee = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-01-01',
            'basic_salary' => 5000000,
            'prorate_enabled' => true,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 15) {
            if (!$date->isWeekend()) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'date' => $date->copy(),
                    'status' => AttendanceLog::STATUS_PRESENT,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'source' => AttendanceLog::SOURCE_WEB,
                ]);
                $daysCreated++;
            }
            $date->addDay();
        }

        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $record = $this->calcService->calculateEmployee($employee, $batch);

        expect($record->details)->toBeArray()
            ->and($record->details)->toHaveKey('salary_structure_id')
            ->and($record->details)->toHaveKey('pph21_method')
            ->and($record->details)->toHaveKey('attendance_summary')
            ->and($record->details)->toHaveKey('prorate_enabled')
            ->and($record->details['prorate_enabled'])->toBeTrue();
    });
});
