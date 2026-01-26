<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GoogleSheetsAttendanceTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'No',
            'Name',
            'Action',
            'Date & Time',
            'Location',
            'Address',
            'City',
            'State/Province',
            'Country',
            'Postal Code',
            'Latitude',
            'Longitude',
            'Photo URL',
            'Work Hours',
            'Description',
        ];
    }

    public function array(): array
    {
        // Sample data rows
        return [
            [
                '1',
                'John Doe',
                'Check In',
                '2024-01-15 08:30:00',
                'Office Building',
                'Jl. Sudirman No. 123',
                'Jakarta',
                'DKI Jakarta',
                'Indonesia',
                '12190',
                '-6.2088',
                '106.8456',
                'https://example.com/photo1.jpg',
                '',
                'Morning check-in',
            ],
            [
                '2',
                'John Doe',
                'Check Out',
                '2024-01-15 17:30:00',
                'Office Building',
                'Jl. Sudirman No. 123',
                'Jakarta',
                'DKI Jakarta',
                'Indonesia',
                '12190',
                '-6.2088',
                '106.8456',
                'https://example.com/photo2.jpg',
                '9 hours',
                'End of day',
            ],
            [
                '3',
                'Jane Smith',
                'Check In',
                '2024-01-15 09:00:00',
                'Home Office',
                'Jl. Gatot Subroto',
                'Jakarta',
                'DKI Jakarta',
                'Indonesia',
                '12930',
                '-6.2297',
                '106.8253',
                '',
                '',
                'Work from home',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  // No
            'B' => 20, // Name
            'C' => 12, // Action
            'D' => 20, // Date & Time
            'E' => 20, // Location
            'F' => 25, // Address
            'G' => 15, // City
            'H' => 15, // State/Province
            'I' => 12, // Country
            'J' => 12, // Postal Code
            'K' => 12, // Latitude
            'L' => 12, // Longitude
            'M' => 30, // Photo URL
            'N' => 12, // Work Hours
            'O' => 25, // Description
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Add instructions in a comment
        $sheet->getComment('A1')->getText()->createTextRun(
            "Google Sheets Attendance Import Template\n\n" .
            "Required columns:\n" .
            "- Name: Employee name (must match name in HRIS)\n" .
            "- Action: 'Check In' or 'Check Out'\n" .
            "- Date & Time: Format YYYY-MM-DD HH:MM:SS or DD/MM/YYYY HH:MM:SS\n\n" .
            "Optional columns:\n" .
            "- Location, Address, City, State/Province, Country, Postal Code\n" .
            "- Latitude, Longitude (GPS coordinates)\n" .
            "- Photo URL (link to photo)\n" .
            "- Work Hours (calculated, for reference only)\n" .
            "- Description (notes)\n\n" .
            "Note: Each row represents one action (check in OR check out).\n" .
            "The system will automatically group check-ins and check-outs by employee and date."
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
            'A1:O100' => [
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
