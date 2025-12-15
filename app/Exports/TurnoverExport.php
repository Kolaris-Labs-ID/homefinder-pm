<?php

namespace App\Exports;

use App\Models\Hris\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TurnoverExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected Carbon $startDate;
    protected Carbon $endDate;

    /**
     * Export turnover data with hire dates, termination dates, and reasons.
     * Supports date range filter.
     * Requirements: 9.2
     */
    public function __construct(Carbon $startDate, Carbon $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        return Employee::query()
            ->whereNotNull('employment_end_date')
            ->whereBetween('employment_end_date', [$this->startDate, $this->endDate])
            ->with([
                'user',
                'primaryPosition.department',
                'primaryPosition.position',
            ])
            ->orderBy('employment_end_date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'NIK',
            'Employee Name',
            'Department',
            'Position',
            'Hire Date',
            'Termination Date',
            'Tenure (Days)',
            'Employment Status',
            'Email',
        ];
    }

    public function map($employee): array
    {
        $tenureDays = $employee->hire_date && $employee->employment_end_date
            ? $employee->hire_date->diffInDays($employee->employment_end_date)
            : 0;

        return [
            $employee->id,
            $employee->nik ?? '',
            $employee->user?->name ?? '',
            $employee->primaryPosition?->department?->name ?? 'Unassigned',
            $employee->primaryPosition?->position?->name ?? '',
            $employee->hire_date?->format('Y-m-d') ?? '',
            $employee->employment_end_date?->format('Y-m-d') ?? '',
            $tenureDays,
            ucfirst(str_replace('_', ' ', $employee->employment_status ?? '')),
            $employee->user?->email ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF8B0000'],
                ],
            ],
        ];
    }
}
