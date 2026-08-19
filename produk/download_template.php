<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (current_user()['role'] !== 'admin') {
    die('Akses ditolak');
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set Header
$headers = ['Merek', 'Kategori', 'Ukuran', 'Kondisi', 'Warna', 'Bahan', 'Modal', 'Harga Jual', 'Deskripsi', 'Sumber Barang', 'Keterangan Sumber'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Add Example Row
$example = ['Levis Vintage', 'Pakaian_Pria', 'L', 'A', 'Biru Navy', 'Denim', 50000, 150000, 'Kondisi mulus', 'Bal Segel X', '-'];
$col = 'A';
foreach ($example as $val) {
    $sheet->setCellValue($col . '2', $val);
    $col++;
}

// Set header for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Template_Import_Produk.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
