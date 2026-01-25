<?php

namespace App\Exports;

use App\Models\Hris\WeeklyAllowanceBatch;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WeeklyAllowanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected WeeklyAllowanceBatch $batch
    ) {}

    public function collection()
    {
        return $this->batch->payments()
            ->with('employee.user')
            ->whereIn('status', ['approved', 'paid'])
            ->where('has_bank_warning', false)
            ->get();
    }

    public function headings(): array
    {
        return [
            'Bank',
            'Account Number',
            'Account Name',
            'Amount',
            'Description',
            'Employee Name',
            'NIK',
            'Days Present',
            'Daily Rate',
        ];
    }

    public function map($record): array
    {
        $weekLabel = $this->batch->week_start_date->format('d') . '-' .
            $this->batch->week_end_date->format('d M Y');

        return [
            $record->bank_name ?? '',
            $record->bank_account_number ?? '',
            $record->bank_account_holder ?? '',
            $record->total_amount,
            "Uang Harian {$weekLabel}",
            $record->employee->user->name ?? '',
            $record->employee->nik ?? '',
            $record->days_present,
            $record->daily_rate,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
