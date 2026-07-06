<?php

namespace App\Services\Hris;

use App\Models\Hris\AttendanceLog;
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

    protected OvertimePayService $overtimePayService;

    protected AttendanceService $attendanceCalculationService;

    protected CompensationResolver $compensationResolver;

    public function __construct(
        protected BpjsCalculationService $bpjsService,
        protected Pph21CalculationService $pph21Service,
        ?AttendancePayrollService $attendanceService = null,
        ?PayrollSettingService $settingService = null,
        ?WeeklyAllowanceService $weeklyAllowanceService = null,
        ?OvertimePayService $overtimePayService = null,
        ?AttendanceService $attendanceCalculationService = null,
        ?CompensationResolver $compensationResolver = null
    ) {
        $this->attendanceService = $attendanceService ?? app(AttendancePayrollService::class);
        $this->settingService = $settingService ?? app(PayrollSettingService::class);
        $this->weeklyAllowanceService = $weeklyAllowanceService ?? app(WeeklyAllowanceService::class);
        $this->overtimePayService = $overtimePayService ?? app(OvertimePayService::class);
        $this->attendanceCalculationService = $attendanceCalculationService ?? app(AttendanceService::class);
        $this->compensationResolver = $compensationResolver ?? app(CompensationResolver::class);
    }

    /**
     * Get the active salary structure for an employee at a given date.
     * Returns the structure with the most recent effective_date that does not exceed the given date.
     */
    public function getActiveSalaryStructure(Employee $employee, Carbon $date): ?SalaryStructure
    {
        return $this->compensationResolver->resolve($employee, $date);
    }

    /**
     * Calculate payroll for a single employee.
     */
    public function calculateEmployee(Employee $employee, PayrunBatch $batch): ?PayrollRecord
    {
        $salaryStructure = $this->getActiveSalaryStructure($employee, $batch->period_end_date);

        if (! $salaryStructure) {
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

        if (! empty($salaryStructure->allowances_variable)) {
            $variableAllowancesResult = $this->attendanceService->calculateVariableAllowances(
                $salaryStructure->allowances_variable,
                $attendanceSummary
            );
            $totalVariableAllowances = $variableAllowancesResult['total'];
        }

        // Calculate daily allowance (Uang Harian) with per-day late penalties applied,
        // resolving the rate effective on each work date so a mid-period rate change
        // applies from its effective_date onward, not retroactively to the whole period.
        $dailyAllowanceTotal = $this->calculateDailyAllowanceWithPenalties(
            $employee,
            $batch->period_start_date,
            $batch->period_end_date
        );

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

            if ($salaryStructure->isBpjsPaidByCompany() && empty($salaryStructure->bpjs_config)) {
                foreach (['kesehatan', 'jht', 'jp'] as $component) {
                    $bpjsConfig[$component]['paid_by'] = 'company';
                }
            }

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

        // Resolve PPh 21 per the structure's method (who bears the tax).
        $pph21Method = $salaryStructure->pph21_method ?? 'gross';
        $taxComponents = $this->pph21Service->taxComponents($taxableIncome, $pph21Method);

        // Gross-up adds a taxable tax allowance (tunjangan pajak) to the employee's
        // gross; withholding the equal tax then leaves take-home unchanged.
        $tunjanganPajak = $taxComponents['tunjangan_pajak'];
        $grossIncome += $tunjanganPajak;

        // PPh 21 actually withheld from the employee (0 when the company bears it).
        $pph21Amount = $taxComponents['employee_withheld'];
        $pph21CompanyBorne = $taxComponents['company_borne'];

        // Calculate other deductions from salary structure (prorated if enabled)
        $otherDeductions = $salaryStructure->total_deductions;
        if ($salaryStructure->prorate_enabled) {
            $otherDeductions = round($otherDeductions * $prorateRatio, 2);
        }

        // Calculate total deductions (employee BPJS + PPh 21 withheld + other)
        $totalDeductions = $bpjs['total_employee'] + $pph21Amount + $otherDeductions;

        // Calculate net income
        $netIncome = $grossIncome - $totalDeductions;

        // Overtime pay: sum of all paid weekly overtime payments within this period
        $overtimePayments = $this->overtimePayService->getMonthlyPayments(
            $employee,
            $batch->period_start_date,
            $batch->period_end_date
        );
        $totalOvertimePay = $overtimePayments
            ->where('status', 'paid')
            ->sum('total_amount');
        $overtimeSessions = $overtimePayments
            ->where('status', 'paid')
            ->map(fn ($p) => [
                'week_label' => $p->batch->week_label ?? '-',
                'sessions' => $p->total_sessions,
                'amount' => (float) $p->total_amount,
            ])->values()->all();

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
            'pph21_company_borne' => $pph21CompanyBorne,
            'tunjangan_pajak' => $tunjanganPajak,
            // Attendance details
            'attendance_summary' => $attendanceSummary,
            'weekend_holiday_work_days' => ($attendanceSummary['weekend_work_days'] ?? 0) + ($attendanceSummary['holiday_work_days'] ?? 0),
            'prorate_enabled' => $salaryStructure->prorate_enabled,
            'daily_allowance_rate' => $salaryStructure->daily_allowance_rate,
            // Weekly allowance reconciliation
            'weekly_allowance_enabled' => $salaryStructure->weekly_allowance_enabled,
            'weekly_allowance_earned' => $weeklyAllowanceEarned,
            'weekly_allowance_paid' => $weeklyAllowancePaid,
            'weekly_allowance_adjustment' => $weeklyAllowanceAdjustment,
            // Overtime pay (paid weekly, shown as informational on payslip)
            'total_overtime_pay' => $totalOvertimePay,
            'overtime_sessions' => $overtimeSessions,
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
                // Overtime pay
                'total_overtime_pay' => $totalOvertimePay,
            ]
        );
    }

    /**
     * Calculate payroll for all eligible employees in a batch.
     */
    public function calculatePayrun(PayrunBatch $batch): array
    {
        if (! $batch->canCalculate()) {
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
     * Submit a calculated payrun batch into the finance approval queue.
     *
     * @deprecated Legacy entry point kept for back-compat/tests; production
     * approvals run through PayrollApprovalService. Canonical post-submit
     * status is `pending_finance` — there is no standalone "approved" state.
     */
    public function approve(PayrunBatch $batch, User $approver): void
    {
        if (! $batch->canApprove()) {
            throw new \Exception("Cannot approve payrun batch with status: {$batch->status}");
        }

        $batch->update([
            'status' => PayrunBatch::STATUS_PENDING_FINANCE,
            'submitted_at' => now(),
            'submitted_by' => $approver->id,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);
    }

    /**
     * Mark a payrun batch as paid.
     */
    public function markAsPaid(PayrunBatch $batch, User $paidBy): void
    {
        if (! $batch->canMarkAsPaid()) {
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

    /**
     * Calculate total daily allowance (Uang Harian) for a period, applying
     * late-attendance deductions per day.
     *
     * For each working day, the daily rate is resolved from the salary structure
     * effective on THAT day (via CompensationResolver), so a mid-period rate change
     * is applied per day rather than to the whole period:
     * - Present/WFH  → full daily rate (of that day)
     * - Late (no notice) → daily rate × (1 - penalty%)
     * - Late (approved notice) → full daily rate
     * - Absent / Half-day → no daily allowance
     */
    protected function calculateDailyAllowanceWithPenalties(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd
    ): float {
        // Load every structure effective on/before period end once, then resolve
        // the rate per work date in memory (no per-day query).
        $structures = $this->compensationResolver->structuresAsOf($employee, $periodEnd);
        if ($structures->isEmpty()) {
            return 0.0;
        }

        $logs = AttendanceLog::forEmployee($employee->id)
            ->forDateRange($periodStart, $periodEnd)
            ->whereIn('status', [
                AttendanceLog::STATUS_PRESENT,
                AttendanceLog::STATUS_LATE,
            ])
            ->get();

        $total = 0.0;
        foreach ($logs as $log) {
            // Skip weekend days and public holiday logs — those are paid via overtime pay instead
            $logDate = Carbon::parse($log->date);
            if ($logDate->isWeekend()) {
                continue;
            }

            $dailyRate = $this->compensationResolver->dailyRateFrom($structures, $logDate);
            if ($dailyRate <= 0) {
                continue;
            }

            if ($log->mode === AttendanceLog::MODE_WFH || $log->status === AttendanceLog::STATUS_PRESENT) {
                $total += $dailyRate;
            } elseif ($log->status === AttendanceLog::STATUS_LATE) {
                $penaltyPercent = $this->attendanceCalculationService->calculateLatePenalty($log);
                $total += $dailyRate * (1 - $penaltyPercent / 100);
            }
        }

        return round($total, 2);
    }
}
