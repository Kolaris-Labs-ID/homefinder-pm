<?php

namespace App\Exports;

use App\Models\Hris\PayrunBatch;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollRecordsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected PayrunBatch $batch
    ) {}

    public function collection()
    {
        return $this->batch->payrollRecords()->with('employee.user')->get();
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'NIK',
            'Bank Name',
            'Bank Account',
            'Basic Salary',
            'Fixed Allowances',
            'Variable Allowances',
            'Gross Income',
            'BPJS Kesehatan (Employee)',
            'BPJS JHT (Employee)',
            'BPJS JP (Employee)',
            'PPh 21',
            'Other Deductions',
            'Total Deductions',
            'Net Income',
            'BPJS Kesehatan (Company)',
            'BPJS JHT (Company)',
            'BPJS JP (Company)',
            'BPJS JKK (Company)',
            'BPJS JKM (Company)',
        ];
    }

    public function map($record): array
    {
        return [
            $record->employee->user->name ?? '',
            $record->employee->nik ?? '',
            $record->employee->bank_name ?? '',
            $record->employee->bank_account_number ?? '',
            $record->basic_salary,
            $record->total_fixed_allowances,
            $record->total_variable_allowances,
            $record->gross_income,
            $record->bpjs_kesehatan_employee,
            $record->bpjs_jht_employee,
            $record->bpjs_jp_employee,
            $record->pph21_amount,
            $record->other_deductions,
            $record->total_deductions,
            $record->net_income,
            $record->bpjs_kesehatan_company,
            $record->bpjs_jht_company,
            $record->bpjs_jp_company,
            $record->bpjs_jkk_company,
            $record->bpjs_jkm_company,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
