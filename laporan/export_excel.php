<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$stmt = $pdo->prepare('SELECT * FROM v_laporan_transaksi WHERE tanggal_jual BETWEEN ? AND ? ORDER BY tanggal_jual DESC, transaksi_id DESC');
$stmt->execute([$start, $end]);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan-penjualan.xls"');

echo "ID Trx\tTanggal\tItem\tKasir\tHarga Jual\tKeuntungan\tMetode Bayar\n";
foreach ($stmt as $row) {
    echo "{$row['transaksi_id']}\t{$row['tanggal_jual']}\t{$row['kode_item']} - {$row['merek']}\t{$row['nama_kasir']}\t{$row['harga_jual']}\t{$row['keuntungan']}\t{$row['metode_bayar']}\n";
}
