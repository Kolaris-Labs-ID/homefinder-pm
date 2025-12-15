<?php

namespace App\Exports;

use App\Models\Hris\PerformanceCycle;
use App\Models\Hris\PerformanceGoal;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PerformanceSummaryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected ?int $cycleId;

    /**
     * Export performance summary including cycle, employee, goals, and scores.
     * Requirements: 9.3
     */
    public function __construct(?int $cycleId = null)
    {
        $this->cycleId = $cycleId;
    }

    public function collection(): Collection
    {
        $query = PerformanceGoal::query()
            ->with([
                'employee.user',
                'cycle',
            ]);

        if ($this->cycleId) {
            $query->where('cycle_id', $this->cycleId);
        }

        return $query
            ->orderBy('cycle_id')
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Cycle Name',
            'Cycle Period',
            'Cycle Status',
            'Employee ID',
            'Employee Name',
            'Goal Title',
            'Goal Description',
            'Weight (%)',
            'Target Value',
            'Actual Value',
            'Score',
            'Progress (%)',
        ];
    }

    public function map($goal): array
    {
        $cyclePeriod = '';
        if ($goal->cycle) {
            $start = $goal->cycle->period_start_date?->format('Y-m-d') ?? '';
            $end = $goal->cycle->period_end_date?->format('Y-m-d') ?? '';
            $cyclePeriod = "{$start} to {$end}";
        }

        return [
            $goal->cycle?->name ?? '',
            $cyclePeriod,
            ucfirst($goal->cycle?->status ?? ''),
            $goal->employee_id,
            $goal->employee?->user?->name ?? '',
            $goal->title,
            $goal->description ?? '',
            $goal->weight,
            $goal->target_value,
            $goal->actual_value ?? '',
            $goal->score ?? '',
            $goal->progress_percentage ?? 0,
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
                    'startColor' => ['argb' => 'FF2E7D32'],
                ],
            ],
        ];
    }
}
