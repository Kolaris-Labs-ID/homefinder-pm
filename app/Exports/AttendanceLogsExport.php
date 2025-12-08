<?php

namespace App\Exports;

use App\Models\Hris\AttendanceLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceLogsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected Collection $attendanceLogs;

    protected array $selectedColumns;

    protected array $availableColumns = [
        'employee' => 'Employee',
        'date' => 'Date',
        'mode' => 'Mode',
        'check_in_time' => 'Check-In Time',
        'check_out_time' => 'Check-Out Time',
        'check_in_address' => 'Check-In Address',
        'check_out_address' => 'Check-Out Address',
        'check_in_latitude' => 'Check-In Latitude',
        'check_in_longitude' => 'Check-In Longitude',
        'check_out_latitude' => 'Check-Out Latitude',
        'check_out_longitude' => 'Check-Out Longitude',
        'source' => 'Source',
        'status' => 'Status',
    ];

    public function __construct(Collection $attendanceLogs, array $selectedColumns)
    {
        $this->attendanceLogs = $attendanceLogs;
        $this->selectedColumns = $selectedColumns;
    }

    public function collection(): Collection
    {
        return $this->attendanceLogs;
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

    public function map($attendanceLog): array
    {
        $row = [];

        foreach ($this->selectedColumns as $column) {
            switch ($column) {
                case 'employee':
                    $row[] = $attendanceLog->employee?->user?->name ?? 'Unknown';
                    break;
                case 'date':
                    $row[] = $attendanceLog->date?->format('Y-m-d') ?? '';
                    break;
                case 'mode':
                    $row[] = match ($attendanceLog->mode) {
                        AttendanceLog::MODE_OFFICE => 'Office',
                        AttendanceLog::MODE_WFH => 'Work From Home',
                        default => $attendanceLog->mode ?? '',
                    };
                    break;
                case 'check_in_time':
                    $row[] = $attendanceLog->check_in_time?->format('Y-m-d H:i:s') ?? '';
                    break;
                case 'check_out_time':
                    $row[] = $attendanceLog->check_out_time?->format('Y-m-d H:i:s') ?? '';
                    break;
                case 'check_in_address':
                    $row[] = $attendanceLog->check_in_address ?? '';
                    break;
                case 'check_out_address':
                    $row[] = $attendanceLog->check_out_address ?? '';
                    break;
                case 'check_in_latitude':
                    $row[] = $attendanceLog->check_in_latitude ?? '';
                    break;
                case 'check_in_longitude':
                    $row[] = $attendanceLog->check_in_longitude ?? '';
                    break;
                case 'check_out_latitude':
                    $row[] = $attendanceLog->check_out_latitude ?? '';
                    break;
                case 'check_out_longitude':
                    $row[] = $attendanceLog->check_out_longitude ?? '';
                    break;
                case 'source':
                    $row[] = match ($attendanceLog->source) {
                        AttendanceLog::SOURCE_WEB => 'Web',
                        AttendanceLog::SOURCE_MOBILE => 'Mobile',
                        AttendanceLog::SOURCE_BIOMETRIC => 'Biometric',
                        default => $attendanceLog->source ?? '',
                    };
                    break;
                case 'status':
                    $row[] = match ($attendanceLog->status) {
                        AttendanceLog::STATUS_PRESENT => 'Present',
                        AttendanceLog::STATUS_LATE => 'Late',
                        AttendanceLog::STATUS_HALF_DAY => 'Half Day',
                        AttendanceLog::STATUS_ABSENT => 'Absent',
                        AttendanceLog::STATUS_LEAVE => 'Leave',
                        AttendanceLog::STATUS_HOLIDAY => 'Holiday',
                        default => $attendanceLog->status ?? '',
                    };
                    break;
                default:
                    $row[] = '';
                    break;
            }
        }

        return $row;
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

    public function getAvailableColumns(): array
    {
        return $this->availableColumns;
    }
}
