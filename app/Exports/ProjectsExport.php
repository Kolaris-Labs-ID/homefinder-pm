<?php

namespace App\Exports;

use App\Support\CsvFormulaGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles
{
    protected Collection|Builder $projects;

    protected array $selectedColumns;

    protected array $availableColumns = [
        'name' => 'Project Name',
        'ticket_prefix' => 'Ticket Prefix',
        'owner' => 'Owner',
        'division' => 'Division',
        'status' => 'Status',
        'is_pinned' => 'Pinned',
        'members_count' => 'Members',
        'tickets_count' => 'Tickets',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'archived_at' => 'Archived Date',
        'archived_by' => 'Archived By',
    ];

    public function __construct(Collection|Builder $projects, array $selectedColumns)
    {
        $this->projects = $projects;
        $this->selectedColumns = array_values(array_intersect($selectedColumns, array_keys($this->availableColumns)));
    }

    public function collection()
    {
        return $this->projects instanceof Builder ? $this->projects->get() : $this->projects;
    }

    public function headings(): array
    {
        $headings = [];
        foreach ($this->selectedColumns as $column) {
            if (isset($this->availableColumns[$column])) {
                $headings[] = $this->availableColumns[$column];
            }
        }

        return $headings;
    }

    public function map($project): array
    {
        $row = [];

        foreach ($this->selectedColumns as $column) {
            switch ($column) {
                case 'name':
                    $row[] = CsvFormulaGuard::escapeXlsx($project->name);
                    break;
                case 'ticket_prefix':
                    $row[] = CsvFormulaGuard::escapeXlsx($project->ticket_prefix ?? '');
                    break;
                case 'owner':
                    $row[] = CsvFormulaGuard::escapeXlsx($project->owner?->name) ?: '-';
                    break;
                case 'division':
                    $row[] = CsvFormulaGuard::escapeXlsx($project->owner?->division) ?: '-';
                    break;
                case 'status':
                    $row[] = match (true) {
                        $project->archived_at !== null => 'Archived',
                        $project->done_at !== null => 'Done',
                        default => 'Active',
                    };
                    break;
                case 'is_pinned':
                    $row[] = $project->pinned_date !== null ? 'Yes' : 'No';
                    break;
                case 'members_count':
                    $row[] = (int) $project->members_count;
                    break;
                case 'tickets_count':
                    $row[] = (int) $project->tickets_count;
                    break;
                case 'created_at':
                    $row[] = $project->created_at?->format('Y-m-d H:i:s') ?? '';
                    break;
                case 'updated_at':
                    $row[] = $project->updated_at?->format('Y-m-d H:i:s') ?? '';
                    break;
                case 'archived_at':
                    $row[] = $project->archived_at?->format('Y-m-d H:i:s') ?? '';
                    break;
                case 'archived_by':
                    $row[] = CsvFormulaGuard::escapeXlsx($project->archivedBy?->name ?? '');
                    break;
                default:
                    $row[] = '';
                    break;
            }
        }

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => [
                        'argb' => 'FFFFFFFF',
                    ],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF366092',
                    ],
                ],
            ],
        ];
    }

    public function getAvailableColumns(): array
    {
        return $this->availableColumns;
    }
}
