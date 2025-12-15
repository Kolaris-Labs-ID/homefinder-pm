<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use Illuminate\Database\Eloquent\Collection;

class PayslipService
{
    /**
     * Generate HTML payslip for a payroll record.
     */
    public function generate(PayrollRecord $record): string
    {
        $record->load(['employee.user', 'payrunBatch']);

        $employee = $record->employee;
        $batch = $record->payrunBatch;

        return view('payslips.template', [
            'record' => $record,
            'employee' => $employee,
            'batch' => $batch,
            'details' => $record->details ?? [],
        ])->render();
    }

    /**
     * Generate PDF payslip with optional password protection.
     * Password defaults to last 6 digits of NIK or DOB (DDMMYY).
     */
    public function generatePdf(PayrollRecord $record, ?string $password = null): string
    {
        $html = $this->generate($record);

        // Generate default password if not provided
        if ($password === null) {
            $password = $this->generateDefaultPassword($record->employee);
        }

        // Create PDF using DomPDF or similar
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);

        // Store PDF temporarily
        $filename = "payslip_{$record->employee_id}_{$record->payrunBatch->period_end_date->format('Y_m')}.pdf";
        $path = "payslips/{$record->employee_id}/{$filename}";

        // Note: Password protection requires additional PDF library support
        // For now, store without password - can be enhanced with TCPDF or similar
        \Storage::disk('private')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Generate default password for payslip PDF.
     */
    protected function generateDefaultPassword(Employee $employee): string
    {
        // Try last 6 digits of NIK first
        if ($employee->nik && strlen($employee->nik) >= 6) {
            return substr($employee->nik, -6);
        }

        // Fall back to DOB in DDMMYY format
        if ($employee->date_of_birth) {
            return $employee->date_of_birth->format('dmy');
        }

        // Default fallback
        return '123456';
    }

    /**
     * Get payslips for an employee (only from paid batches).
     */
    public function getEmployeePayslips(Employee $employee): Collection
    {
        return PayrollRecord::forEmployee($employee->id)
            ->whereHas('payrunBatch', function ($query) {
                $query->where('status', PayrunBatch::STATUS_PAID);
            })
            ->with('payrunBatch')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if a payslip is available for viewing.
     */
    public function isPayslipAvailable(PayrollRecord $record): bool
    {
        return $record->payrunBatch &&
            $record->payrunBatch->status === PayrunBatch::STATUS_PAID;
    }

    /**
     * Get payslip summary data for API response.
     */
    public function getPayslipSummary(PayrollRecord $record): array
    {
        return [
            'id' => $record->id,
            'period' => [
                'start' => $record->payrunBatch->period_start_date->toDateString(),
                'end' => $record->payrunBatch->period_end_date->toDateString(),
                'pay_date' => $record->payrunBatch->pay_date->toDateString(),
            ],
            'earnings' => [
                'basic_salary' => (float) $record->basic_salary,
                'fixed_allowances' => (float) $record->total_fixed_allowances,
                'variable_allowances' => (float) $record->total_variable_allowances,
                'gross_income' => (float) $record->gross_income,
            ],
            'deductions' => [
                'bpjs_kesehatan' => (float) $record->bpjs_kesehatan_employee,
                'bpjs_jht' => (float) $record->bpjs_jht_employee,
                'bpjs_jp' => (float) $record->bpjs_jp_employee,
                'pph21' => (float) $record->pph21_amount,
                'other' => (float) $record->other_deductions,
                'total' => (float) $record->total_deductions,
            ],
            'company_contributions' => [
                'bpjs_kesehatan' => (float) $record->bpjs_kesehatan_company,
                'bpjs_jht' => (float) $record->bpjs_jht_company,
                'bpjs_jp' => (float) $record->bpjs_jp_company,
                'bpjs_jkk' => (float) $record->bpjs_jkk_company,
                'bpjs_jkm' => (float) $record->bpjs_jkm_company,
            ],
            'net_income' => (float) $record->net_income,
        ];
    }
}
