<?php

namespace App\Exports;

use App\Models\Project;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketsTimelineGantt implements FromArray, WithHeadings, WithStyles, WithEvents, WithTitle, ShouldAutoSize, WithColumnFormatting
{
    protected $project;
    protected $ganttData;
    protected $dateRange;
    protected $totalDays;
    protected $startDate;
    protected $endDate;

    public function __construct(Project $project, array $ganttData)
    {
        $this->project = $project;
        $this->ganttData = $ganttData;
        $this->calculateDateRange();
    }

    private function calculateDateRange()
    {
        if (empty($this->ganttData['data'])) {
            $this->startDate = Carbon::now();
            $this->endDate = Carbon::now()->addDays(30);
            $this->totalDays = 30;
            $this->dateRange = [];
            return;
        }

        $dates = [];
        foreach ($this->ganttData['data'] as $task) {
            // Parse the date format from gantt (d-m-Y H:i)
            $startDateParts = explode(' ', $task['start_date'])[0];
            $endDateParts = explode(' ', $task['end_date'])[0];

            $startDate = Carbon::createFromFormat('d-m-Y', $startDateParts);
            $endDate = Carbon::createFromFormat('d-m-Y', $endDateParts);

            $dates[] = $startDate;
            $dates[] = $endDate;
        }

        $this->startDate = collect($dates)->min();
        $this->endDate = collect($dates)->max();

        // Add some padding
        $this->startDate = $this->startDate->subDays(2);
        $this->endDate = $this->endDate->addDays(2);

        $this->totalDays = $this->startDate->diffInDays($this->endDate) + 1;

        // Generate date range array
        $this->dateRange = [];
        $currentDate = $this->startDate->copy();
        for ($i = 0; $i < $this->totalDays; $i++) {
            $this->dateRange[] = $currentDate->copy();
            $currentDate->addDay();
        }
    }

    public function array(): array
    {
        $data = [];

        // Add project info rows
        $data[] = [$this->project->name . ' - Gantt Chart Timeline'];
        $data[] = ['Generated on: ' . Carbon::now()->format('F d, Y H:i')];
        $data[] = ['Date Range: ' . $this->startDate->format('M d, Y') . ' - ' . $this->endDate->format('M d, Y')];
        $data[] = []; // Empty row

        // Add gantt data rows
        foreach ($this->ganttData['data'] as $task) {
            $row = [$task['ticket_details']['name']];

            // Parse task dates
            $taskStartParts = explode(' ', $task['start_date'])[0];
            $taskEndParts = explode(' ', $task['end_date'])[0];

            $taskStart = Carbon::createFromFormat('d-m-Y', $taskStartParts);
            $taskEnd = Carbon::createFromFormat('d-m-Y', $taskEndParts);

            // Fill timeline cells
            foreach ($this->dateRange as $date) {
                if ($date->between($taskStart, $taskEnd)) {
                    // Task is active on this date
                    $row[] = '■'; // Will be replaced with background color
                } else {
                    $row[] = '';
                }
            }

            $data[] = $row;
        }

        return $data;
    }

    public function headings(): array
    {
        $headings = ['Task Name'];

        // Add date headings
        foreach ($this->dateRange as $date) {
            $headings[] = $date->format('M j');
        }

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = chr(65 + count($this->dateRange)); // A + number of date columns
        $dataStartRow = 6; // After project info and headers
        $lastRow = $dataStartRow + count($this->ganttData['data']) - 1;

        return [
            // Title styling
            1 => [
                'font' => ['bold' => true, 'size' => 16],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ],
            2 => [
                'font' => ['size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ],
            3 => [
                'font' => ['size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ],

            // Header row styling
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '366092']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ],

            // Task name column styling
            "A{$dataStartRow}:A{$lastRow}" => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ],

            // Timeline area borders
            "B{$dataStartRow}:{$lastColumn}{$lastRow}" => [
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->styleGanttChart($event->sheet->getDelegate());
            },
        ];
    }

    private function styleGanttChart(Worksheet $sheet)
    {
        $dataStartRow = 6;
        $taskIndex = 0;

        foreach ($this->ganttData['data'] as $task) {
            $currentRow = $dataStartRow + $taskIndex;

            // Get task color from status
            $statusColor = $task['ticket_details']['status']['color'] ?? '#3B82F6';
            $statusColor = ltrim($statusColor, '#'); // Remove # if present

            // Parse task dates
            $taskStartParts = explode(' ', $task['start_date'])[0];
            $taskEndParts = explode(' ', $task['end_date'])[0];

            $taskStart = Carbon::createFromFormat('d-m-Y', $taskStartParts);
            $taskEnd = Carbon::createFromFormat('d-m-Y', $taskEndParts);

            // Apply colors to timeline cells
            foreach ($this->dateRange as $dateIndex => $date) {
                $column = chr(66 + $dateIndex); // B, C, D, etc.
                $cell = $column . $currentRow;

                if ($date->between($taskStart, $taskEnd)) {
                    // Apply status color as background
                    $sheet->getStyle($cell)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => $statusColor]
                        ]
                    ]);

                    // Clear the cell content (remove ■ character)
                    $sheet->setCellValue($cell, '');
                }
            }

            $taskIndex++;
        }

        // Style header dates with better formatting
        $headerRow = 5;
        for ($i = 0; $i < count($this->dateRange); $i++) {
            $column = chr(66 + $i); // B, C, D, etc.
            $cell = $column . $headerRow;

            $date = $this->dateRange[$i];

            // Highlight weekends
            if ($date->isWeekend()) {
                $sheet->getStyle($cell)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'F3F4F6']
                    ]
                ]);
            }

            // Add month separators
            if ($date->day === 1) {
                $sheet->getStyle($cell)->applyFromArray([
                    'borders' => [
                        'left' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => '1F2937']]
                    ]
                ]);
            }
        }

        // Add legend
        $this->addLegend($sheet, $dataStartRow + count($this->ganttData['data']) + 2);
    }

    private function addLegend(Worksheet $sheet, int $startRow)
    {
        // Get unique statuses from gantt data
        $statuses = [];
        foreach ($this->ganttData['data'] as $task) {
            $status = $task['ticket_details']['status'];
            $statuses[$status['name']] = $status['color'] ?? '#3B82F6';
        }

        $sheet->setCellValue('A' . $startRow, 'Legend:');
        $sheet->getStyle('A' . $startRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12]
        ]);

        $legendRow = $startRow + 1;
        $col = 0;

        foreach ($statuses as $statusName => $color) {
            $column = chr(65 + $col); // A, B, C, etc.

            // Status color cell
            $colorCell = $column . $legendRow;
            $sheet->setCellValue($colorCell, '   ');
            $sheet->getStyle($colorCell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => ltrim($color, '#')]
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]);

            // Status name cell
            $nameColumn = chr(65 + $col + 1);
            $nameCell = $nameColumn . $legendRow;
            $sheet->setCellValue($nameCell, $statusName);
            $sheet->getStyle($nameCell)->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ]);

            $col += 3; // Space between legend items
        }
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@', // Text format for task names
        ];
    }

    public function title(): string
    {
        return 'Gantt Timeline';
    }
}