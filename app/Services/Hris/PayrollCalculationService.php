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
    public function __construct(
        protected BpjsCalculationService $bpjsService,
        protected Pph21CalculationService $pph21Service
    ) {}

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

        // Calculate gross income
        $basicSalary = (float) $salaryStructure->basic_salary;
        $totalFixedAllowances = $salaryStructure->total_fixed_allowances;
        $totalVariableAllowances = $salaryStructure->total_variable_allowances;
        $grossIncome = $basicSalary + $totalFixedAllowances + $totalVariableAllowances;

        // Calculate BPJS components
        $bpjs = $this->bpjsService->calculateAll($grossIncome, $salaryStructure->jkk_risk_level);

        // Calculate taxable income (gross - employee BPJS contributions)
        $taxableIncome = $grossIncome - $bpjs['total_employee'];

        // Calculate PPh 21
        $pph21Amount = $this->pph21Service->calculateMonthlyTax(
            $taxableIncome,
            $salaryStructure->pph21_method
        );

        // Calculate other deductions from salary structure
        $otherDeductions = $salaryStructure->total_deductions;

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
            'allowances_variable' => $salaryStructure->allowances_variable ?? [],
            'deductions' => $salaryStructure->deductions ?? [],
            'bpjs_breakdown' => $bpjs,
            'pph21_rate' => $this->pph21Service->getTerRate($taxableIncome),
        ];

        // Create or update payroll record
        return PayrollRecord::updateOrCreate(
            [
                'payrun_batch_id' => $batch->id,
                'employee_id' => $employee->id,
            ],
            [
                'basic_salary' => $basicSalary,
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
