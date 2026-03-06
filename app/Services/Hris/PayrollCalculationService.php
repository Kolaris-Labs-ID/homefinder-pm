<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    protected AttendancePayrollService $attendanceService;
    protected PayrollSettingService $settingService;
    protected WeeklyAllowanceService $weeklyAllowanceService;

    public function __construct(
        protected BpjsCalculationService $bpjsService,
        protected Pph21CalculationService $pph21Service,
        ?AttendancePayrollService $attendanceService = null,
        ?PayrollSettingService $settingService = null,
        ?WeeklyAllowanceService $weeklyAllowanceService = null
    ) {
        $this->attendanceService = $attendanceService ?? app(AttendancePayrollService::class);
        $this->settingService = $settingService ?? app(PayrollSettingService::class);
        $this->weeklyAllowanceService = $weeklyAllowanceService ?? app(WeeklyAllowanceService::class);
    }

    /**
     * Get the active salary structure for an employee at a given date.
     * Returns the structure with the most recent effective_date that does not exceed the given date.
     */
    public function getActiveSalaryStructure(Employee $employee, Carbon $date): ?SalaryStructure
    {
        return SalaryStructure::forEmployee($employee->id)
            ->activeForDate($date)
            ->first();
    }

    /**
     * Calculate payroll for a single employee.
     */
    public function calculateEmployee(Employee $employee, PayrunBatch $batch): ?PayrollRecord
    {
        $salaryStructure = $this->getActiveSalaryStructure($employee, $batch->period_end_date);

        if (!$salaryStructure) {
            return null;
        }

        // Get attendance summary for the period
        $attendanceSummary = $this->attendanceService->getAttendanceSummary(
            $employee,
            $batch->period_start_date,
            $batch->period_end_date
        );

        // Calculate prorate information
        $totalWorkingDays = $attendanceSummary['total_working_days'];
        $actualDaysWorked = (int) $this->attendanceService->calculateActualDaysWorked($attendanceSummary);

        // Calculate prorate ratio if prorate is enabled
        $prorateRatio = 1.0;
        if ($salaryStructure->prorate_enabled) {
            $prorateRatio = $this->attendanceService->calculateProrateRatio($actualDaysWorked, $totalWorkingDays);
        }

        // Calculate basic salary (with prorate)
        $fullBasicSalary = (float) $salaryStructure->basic_salary;
        $proratedBasicSalary = $salaryStructure->prorate_enabled
            ? $this->attendanceService->calculateProratedSalary($fullBasicSalary, $prorateRatio)
            : $fullBasicSalary;

        // Calculate fixed allowances (prorated if enabled)
        $totalFixedAllowances = $salaryStructure->total_fixed_allowances;
        if ($salaryStructure->prorate_enabled) {
            $totalFixedAllowances = round($totalFixedAllowances * $prorateRatio, 2);
        }

        // Calculate variable allowances (attendance-based)
        $variableAllowancesResult = [];
        $totalVariableAllowances = 0;

        if (!empty($salaryStructure->allowances_variable)) {
            $variableAllowancesResult = $this->attendanceService->calculateVariableAllowances(
                $salaryStructure->allowances_variable,
                $attendanceSummary
            );
            $totalVariableAllowances = $variableAllowancesResult['total'];
        }

        // Calculate daily allowance (Uang Harian)
        $dailyAllowanceTotal = 0;
        if ($salaryStructure->daily_allowance_rate && $salaryStructure->daily_allowance_rate > 0) {
            $dailyAllowanceTotal = round($salaryStructure->daily_allowance_rate * $actualDaysWorked, 2);
        }

        // Weekly allowance reconciliation
        $weeklyAllowanceEarned = $dailyAllowanceTotal;
        $weeklyAllowancePaid = 0;
        $weeklyAllowanceAdjustment = 0;

        // If employee has weekly allowance enabled, calculate how much was already paid
        if ($salaryStructure->weekly_allowance_enabled) {
            $weeklyAllowancePaid = $this->weeklyAllowanceService->getMonthlyPaidTotal(
                $employee,
                $batch->period_start_date,
                $batch->period_end_date
            );
            $weeklyAllowanceAdjustment = $weeklyAllowanceEarned - $weeklyAllowancePaid;
        }

        // Calculate gross income
        $grossIncome = $proratedBasicSalary + $totalFixedAllowances + $totalVariableAllowances + $dailyAllowanceTotal;

        // Determine if BPJS is company paid (legacy fallback)
        $bpjsCompanyPaid = $salaryStructure->isBpjsPaidByCompany();

        // Calculate BPJS components using per-component config
        $bpjsBaseSalary = $fullBasicSalary + $salaryStructure->total_fixed_allowances;
        if ($salaryStructure->isBpjsEnabled()) {
            $bpjsConfig = $salaryStructure->getBpjsConfig();
            $bpjs = $this->bpjsService->calculateAllPerComponent(
                $bpjsBaseSalary,
                $salaryStructure->jkk_risk_level ?? 'low',
                $bpjsConfig
            );
        } else {
            $bpjs = [
                'kesehatan' => ['employee' => 0.0, 'company' => 0.0],
                'jht' => ['employee' => 0.0, 'company' => 0.0],
                'jp' => ['employee' => 0.0, 'company' => 0.0],
                'jkk' => 0.0,
                'jkm' => 0.0,
                'total_employee' => 0.0,
                'total_company' => 0.0,
                'grand_total' => 0.0,
                'component_config' => [],
            ];
            $bpjsCompanyPaid = false;
        }

        // Calculate taxable income (gross - employee BPJS contributions)
        $taxableIncome = $grossIncome - $bpjs['total_employee'];

        // Calculate PPh 21
        $pph21Amount = $this->pph21Service->calculateMonthlyTax(
            $taxableIncome,
            $salaryStructure->pph21_method ?? 'gross'
        );

        // Calculate other deductions from salary structure (prorated if enabled)
        $otherDeductions = $salaryStructure->total_deductions;
        if ($salaryStructure->prorate_enabled) {
            $otherDeductions = round($otherDeductions * $prorateRatio, 2);
        }

        // Calculate total deductions (employee BPJS + PPh 21 + other)
        $totalDeductions = $bpjs['total_employee'] + $pph21Amount + $otherDeductions;

        // Calculate net income
        $netIncome = $grossIncome - $totalDeductions;

        // Build detailed breakdown
        $details = [
            'salary_structure_id' => $salaryStructure->id,
            'effective_date' => $salaryStructure->effective_date->toDateString(),
            'pph21_method' => $salaryStructure->pph21_method,
            'jkk_risk_level' => $salaryStructure->jkk_risk_level,
            'allowances_fixed' => $salaryStructure->allowances_fixed ?? [],
            'allowances_variable' => $variableAllowancesResult['breakdown'] ?? [],
            'deductions' => $salaryStructure->deductions ?? [],
            'bpjs_breakdown' => $bpjs,
            'bpjs_paid_by' => $salaryStructure->bpjs_paid_by,
            'pph21_rate' => $this->pph21Service->getTerRate($taxableIncome),
            // Attendance details
            'attendance_summary' => $attendanceSummary,
            'prorate_enabled' => $salaryStructure->prorate_enabled,
            'daily_allowance_rate' => $salaryStructure->daily_allowance_rate,
            // Weekly allowance reconciliation
            'weekly_allowance_enabled' => $salaryStructure->weekly_allowance_enabled,
            'weekly_allowance_earned' => $weeklyAllowanceEarned,
            'weekly_allowance_paid' => $weeklyAllowancePaid,
            'weekly_allowance_adjustment' => $weeklyAllowanceAdjustment,
        ];

        // Create or update payroll record
        return PayrollRecord::updateOrCreate(
            [
                'payrun_batch_id' => $batch->id,
                'employee_id' => $employee->id,
            ],
            [
                'basic_salary' => $proratedBasicSalary,
                'total_fixed_allowances' => $totalFixedAllowances,
                'total_variable_allowances' => $totalVariableAllowances,
                'gross_income' => $grossIncome,
                'taxable_income' => $taxableIncome,
                'bpjs_kesehatan_employee' => $bpjs['kesehatan']['employee'],
                'bpjs_kesehatan_company' => $bpjs['kesehatan']['company'],
                'bpjs_jht_employee' => $bpjs['jht']['employee'],
                'bpjs_jht_company' => $bpjs['jht']['company'],
                'bpjs_jp_employee' => $bpjs['jp']['employee'],
                'bpjs_jp_company' => $bpjs['jp']['company'],
                'bpjs_jkk_company' => $bpjs['jkk'],
                'bpjs_jkm_company' => $bpjs['jkm'],
                'pph21_amount' => $pph21Amount,
                'other_deductions' => $otherDeductions,
                'total_deductions' => $totalDeductions,
                'net_income' => $netIncome,
                'details' => $details,
                // Prorate fields
                'total_working_days' => $totalWorkingDays,
                'actual_days_worked' => $actualDaysWorked,
                'prorate_ratio' => $prorateRatio,
                'full_basic_salary' => $fullBasicSalary,
                'prorated_basic_salary' => $proratedBasicSalary,
                'daily_allowance_total' => $dailyAllowanceTotal,
                'bpjs_company_paid' => $bpjsCompanyPaid,
                // Weekly allowance reconciliation
                'weekly_allowance_earned' => $weeklyAllowanceEarned,
                'weekly_allowance_paid' => $weeklyAllowancePaid,
                'weekly_allowance_adjustment' => $weeklyAllowanceAdjustment,
            ]
        );
    }

    /**
     * Calculate payroll for all eligible employees in a batch.
     */
    public function calculatePayrun(PayrunBatch $batch): array
    {
        if (!$batch->canCalculate()) {
            throw new \Exception("Cannot calculate payrun batch with status: {$batch->status}");
        }

        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get all active employees
        $employees = Employee::active()->get();

        DB::transaction(function () use ($batch, $employees, &$results) {
            foreach ($employees as $employee) {
                try {
                    $record = $this->calculateEmployee($employee, $batch);

                    if ($record) {
                        $results['processed']++;
                    } else {
                        $results['skipped']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'employee_id' => $employee->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Update batch status
            $batch->update([
                'status' => PayrunBatch::STATUS_CALCULATED,
                'calculated_at' => now(),
            ]);
        });

        return $results;
    }

    /**
     * Approve a payrun batch.
     */
    public function approve(PayrunBatch $batch, User $approver): void
    {
        if (!$batch->canApprove()) {
            throw new \Exception("Cannot approve payrun batch with status: {$batch->status}");
        }

        $batch->update([
            'status' => PayrunBatch::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);
    }

    /**
     * Mark a payrun batch as paid.
     */
    public function markAsPaid(PayrunBatch $batch, User $paidBy): void
    {
        if (!$batch->canMarkAsPaid()) {
            throw new \Exception("Cannot mark as paid payrun batch with status: {$batch->status}");
        }

        $batch->update([
            'status' => PayrunBatch::STATUS_PAID,
            'paid_at' => now(),
            'paid_by' => $paidBy->id,
        ]);
    }

    /**
     * Recalculate a draft payrun batch.
     * This is idempotent - running it multiple times produces the same result.
     */
    public function recalculate(PayrunBatch $batch): array
    {
        if ($batch->isLocked()) {
            throw new \Exception("Cannot recalculate locked payrun batch with status: {$batch->status}");
        }

        // Reset status to draft if it was calculated
        if ($batch->status === PayrunBatch::STATUS_CALCULATED) {
            $batch->update([
                'status' => PayrunBatch::STATUS_DRAFT,
                'calculated_at' => null,
            ]);
        }

        return $this->calculatePayrun($batch);
    }
}
