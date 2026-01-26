<?php
// Test script to analyze Excel structure
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = 'docs/Attendance.xlsx';

echo "=== Analyzing Attendance.xlsx ===\n\n";

$reader = IOFactory::createReaderForFile($filePath);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($filePath);
$worksheet = $spreadsheet->getActiveSheet();

echo "Total rows: " . $worksheet->getHighestRow() . "\n";
echo "Columns: " . $worksheet->getHighestColumn() . "\n\n";

// Find the header row by looking for "No" or "Name" in first 10 rows
echo "=== Scanning first 10 rows for headers ===\n";
for ($row = 1; $row <= 10; $row++) {
    $rowData = [];
    for ($col = 1; $col <= 20; $col++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $val = $worksheet->getCell($colLetter . $row)->getValue();
        if ($val !== null && $val !== '') {
            $rowData[$colLetter] = $val;
        }
    }
    if (!empty($rowData)) {
        echo "Row $row: " . json_encode($rowData, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// Find the actual header row
$headerRow = null;
for ($row = 1; $row <= 10; $row++) {
    $cellA = $worksheet->getCell('A' . $row)->getValue();
    $cellB = $worksheet->getCell('B' . $row)->getValue();
    if (strtolower(trim($cellA ?? '')) === 'no' || strtolower(trim($cellB ?? '')) === 'name') {
        $headerRow = $row;
        break;
    }
}

if ($headerRow) {
    echo "\n=== Found headers at row $headerRow ===\n";
    for ($col = 1; $col <= 20; $col++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $val = $worksheet->getCell($colLetter . $headerRow)->getValue();
        if ($val !== null && $val !== '') {
            echo "$colLetter: $val\n";
        }
    }

    // Show sample data rows
    echo "\n=== Sample data (rows " . ($headerRow + 1) . "-" . ($headerRow + 3) . ") ===\n";
    for ($row = $headerRow + 1; $row <= $headerRow + 3; $row++) {
        echo "Row $row:\n";
        for ($col = 1; $col <= 15; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $val = $worksheet->getCell($colLetter . $row)->getValue();
            if ($val !== null && $val !== '') {
                echo "  $colLetter: $val\n";
            }
        }
    }
} else {
    echo "\nCould not find header row automatically.\n";
}
