<?php

namespace App\Exports;

use App\Models\Project;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use App\Models\Epic;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketTemplateExport implements WithMultipleSheets
{
    protected $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function sheets(): array
    {
        return [
            new TicketTemplateSheet($this->project),
            new ProjectMembersSheet($this->project),
            new ReferenceDataSheet($this->project),
        ];
    }
}

class TicketTemplateSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function title(): string
    {
        return 'Ticket Template';
    }

    public function headings(): array
    {
        return [
            'Title',
            'Description',
            'Status',
            'Priority', 
            'Epic',
            'Assignees Comma Separated Emails',
            'Start Date YYYY-MM-DD',
            'Due Date YYYY-MM-DD',
        ];
    }

    public function array(): array
    {
        // Return sample data rows
        return [
            [
                'Sample Ticket Title',
                'Sample description for the ticket',
                'To Do',
                'Medium',
                '',
                'user@example.com, user2@example.com',
                '2024-01-01',
                '2024-12-31',
            ],
            // Add more sample rows if needed
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF366092',
                    ],
                ],
                'font' => [
                    'color' => [
                        'argb' => 'FFFFFFFF',
                    ],
                    'bold' => true,
                ],
            ],
        ];
    }
}

class ProjectMembersSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function title(): string
    {
        return 'Project Members';
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Role',
        ];
    }

    public function array(): array
    {
        $members = $this->project->members()->with('roles')->get();
        $data = [];

        foreach ($members as $member) {
            $data[] = [
                $member->name,
                $member->email,
                $member->roles->pluck('name')->implode(', '),
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF28A745',
                    ],
                ],
                'font' => [
                    'color' => [
                        'argb' => 'FFFFFFFF',
                    ],
                    'bold' => true,
                ],
            ],
        ];
    }
}

class ReferenceDataSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function title(): string
    {
        return 'Reference Data';
    }

    public function headings(): array
    {
        return [
            'Available Statuses',
            'Available Priorities',
            'Available Epics',
        ];
    }

    public function array(): array
    {
        $statuses = $this->project->ticketStatuses()->pluck('name')->toArray();
        $priorities = TicketPriority::pluck('name')->toArray();
        $epics = $this->project->epics()->pluck('name')->toArray();

        $maxRows = max(count($statuses), count($priorities), count($epics));
        $data = [];

        for ($i = 0; $i < $maxRows; $i++) {
            $data[] = [
                $statuses[$i] ?? '',
                $priorities[$i] ?? '',
                $epics[$i] ?? '',
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FFDC3545',
                    ],
                ],
                'font' => [
                    'color' => [
                        'argb' => 'FFFFFFFF',
                    ],
                    'bold' => true,
                ],
            ],
        ];
    }
}