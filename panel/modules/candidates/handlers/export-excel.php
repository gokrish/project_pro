<?php
/**
 * Export Candidates to Excel
 * Uses PhpSpreadsheet library
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;

// Check permission
Permission::require('candidates', 'view');

// Note: For production, install PhpSpreadsheet:
// composer require phpoffice/phpspreadsheet

// For now, redirect to CSV export
// In production, implement Excel export with PhpSpreadsheet

header('Location: /panel/modules/candidates/handlers/export-csv.php?' . http_build_query($_GET));
exit;

/*
// Full Excel implementation example:

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Get data (same as CSV export)
// ...

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers with styling
$headers = ['Candidate Code', 'Name', 'Email', ...];
$sheet->fromArray($headers, null, 'A1');

// Style header row
$headerStyle = $sheet->getStyle('A1:P1');
$headerStyle->getFont()->setBold(true);
$headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('667EEA');
$headerStyle->getFont()->getColor()->setRGB('FFFFFF');

// Write data
$row = 2;
foreach ($candidates as $candidate) {
    $sheet->fromArray([...], null, 'A' . $row);
    $row++;
}

// Auto-size columns
foreach (range('A', 'P') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="candidates_' . date('Y-m-d_His') . '.xlsx"');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
*/