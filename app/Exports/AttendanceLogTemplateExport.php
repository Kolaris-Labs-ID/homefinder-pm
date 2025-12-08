<?php

namespace App\Exports;

use App\Models\Hris\AttendanceLog;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AttendanceLogTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'employee_email',
            'date',
            'check_in_time',
            'check_out_time',
            'mode',
            'status',
            'source',
            'check_in_latitude',
            'check_in_longitude',
            'check_in_address',
            'check_out_latitude',
            'check_out_longitude',
            'check_out_address',
        ];
    }

    public function array(): array
    {
        // Sample data rows
        return [
            [
                'employee@example.com',
                '2024-01-15',
                '08:30',
                '17:30',
                'office',
                'present',
                'web',
                '-6.2088',
                '106.8456',
                'Office Building, Jakarta',
                '-6.2088',
                '106.8456',
                'Office Building, Jakarta',
            ],
            [
                'employee2@example.com',
                '2024-01-15',
                '09:00',
                '18:00',
                'wfh',
                'present',
                'mobile',
                '',
                '',
                'Home',
                '',
                '',
                'Home',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25, // employee_email
            'B' => 12, // date
            'C' => 12, // check_in_time
            'D' => 12, // check_out_time
            'E' => 10, // mode
            'F' => 10, // status
            'G' => 10, // source
            'H' => 15, // check_in_latitude
            'I' => 15, // check_in_longitude
            'J' => 25, // check_in_address
            'K' => 15, // check_out_latitude
            'L' => 15, // check_out_longitude
            'M' => 25, // check_out_address
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Add instructions in a comment or separate area
        $sheet->getComment('A1')->getText()->createTextRun(
            "Required: Employee email must exist in the system.\n" .
            "Date format: YYYY-MM-DD\n" .
            "Time format: HH:MM or HH:MM:SS\n" .
            "Mode: office, wfh\n" .
            "Status: present, late, half_day, absent, leave, holiday\n" .
            "Source: web, mobile, biometric"
        );

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            'A1:M100' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ],
        ];
    }
}
