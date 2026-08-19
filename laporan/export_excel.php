<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

if (!in_array(current_user()['role'], ['pemilik', 'admin'])) {
    die('Akses ditolak');
}

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT 
        tanggal_jual, 
        COUNT(transaksi_id) as trx, 
        SUM(harga_jual) as omzet, 
        SUM(modal) as modal, 
        SUM(keuntungan) as keuntungan 
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    WHERE tanggal_jual BETWEEN ? AND ?
    GROUP BY tanggal_jual 
    ORDER BY tanggal_jual ASC
");
$stmt->execute([$start, $end]);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="rekap-harian-'.$start.'-sd-'.$end.'.xls"');

echo "Tanggal\tJml Transaksi\tTotal Omzet\tTotal Modal\tTotal Keuntungan\n";
foreach ($stmt as $row) {
    echo "{$row['tanggal_jual']}\t{$row['trx']}\t{$row['omzet']}\t{$row['modal']}\t{$row['keuntungan']}\n";
}
