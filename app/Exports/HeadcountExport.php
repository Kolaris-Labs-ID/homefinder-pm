<?php

namespace App\Exports;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HeadcountExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * Export employee details grouped by department.
     * Supports CSV/XLSX formats.
     * Requirements: 9.1
     */
    public function collection(): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->with([
                'user',
                'primaryPosition.department',
                'primaryPosition.position',
            ])
            ->orderBy('id')
            ->get()
            ->sortBy(fn ($employee) => $employee->primaryPosition?->department?->name ?? 'ZZZ');
    }

    public function headings(): array
    {
        return [
            'Department',
            'Employee ID',
            'NIK',
            'Employee Name',
            'Position',
            'Employment Status',
            'Hire Date',
            'Gender',
            'Date of Birth',
            'Email',
            'Phone',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->primaryPosition?->department?->name ?? 'Unassigned',
            $employee->id,
            $employee->nik ?? '',
            $employee->user?->name ?? '',
            $employee->primaryPosition?->position?->name ?? '',
            ucfirst(str_replace('_', ' ', $employee->employment_status ?? '')),
            $employee->hire_date?->format('Y-m-d') ?? '',
            ucfirst($employee->gender ?? ''),
            $employee->date_of_birth?->format('Y-m-d') ?? '',
            $employee->user?->email ?? '',
            $employee->phone ?? '',
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
                    'startColor' => ['argb' => 'FF366092'],
                ],
            ],
        ];
    }
}
