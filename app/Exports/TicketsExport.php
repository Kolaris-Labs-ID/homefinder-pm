<?php

namespace App\Exports;

use App\Support\CsvFormulaGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected Collection|Builder $tickets;

    protected $selectedColumns;

    protected $availableColumns = [
        'uuid' => 'Ticket ID',
        'name' => 'Title',
        'description' => 'Description',
        'status' => 'Status',
        'assignee' => 'Assignee',
        'project' => 'Project',
        'epic' => 'Epic',
        'start_date' => 'Start Date',
        'due_date' => 'Due Date',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ];

    public function __construct(Collection|Builder $tickets, array $selectedColumns)
    {
        $this->tickets = $tickets;
        // headings() skips unknown keys; map() must skip the same ones or the
        // written sheet ships one data cell wider than its header row.
        $this->selectedColumns = array_values(array_intersect($selectedColumns, array_keys($this->availableColumns)));
    }

    public function collection()
    {
        return $this->tickets instanceof Builder ? $this->tickets->get() : $this->tickets;
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

    public function map($ticket): array
    {
        $row = [];

        foreach ($this->selectedColumns as $column) {
            switch ($column) {
                case 'uuid':
                    $row[] = CsvFormulaGuard::escapeXlsx($ticket->uuid);
                    break;
                case 'name':
                    $row[] = CsvFormulaGuard::escapeXlsx($ticket->name);
                    break;
                case 'description':
                    $row[] = CsvFormulaGuard::escapeXlsx(strip_tags($ticket->description ?? ''));
                    break;
                case 'status':
                    $row[] = CsvFormulaGuard::escapeXlsx($ticket->status?->name ?? 'No Status');
                    break;
                case 'assignee':
                    $row[] = CsvFormulaGuard::escapeXlsx($ticket->assignees->pluck('name')->implode(', '));
                    break;
                case 'project':
                    $row[] = CsvFormulaGuard::escapeXlsx($ticket->project?->name ?? 'No Project');
                    break;
                case 'epic':
                    $row[] = CsvFormulaGuard::escapeXlsx($ticket->epic?->name ?? 'No Epic');
                    break;
                case 'start_date':
                    $row[] = $ticket->start_date ? $ticket->start_date->format('Y-m-d') : '';
                    break;
                case 'due_date':
                    $row[] = $ticket->due_date ? $ticket->due_date->format('Y-m-d') : '';
                    break;
                case 'created_at':
                    $row[] = $ticket->created_at?->format('Y-m-d H:i:s') ?? '';
                    break;
                case 'updated_at':
                    $row[] = $ticket->updated_at?->format('Y-m-d H:i:s') ?? '';
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
