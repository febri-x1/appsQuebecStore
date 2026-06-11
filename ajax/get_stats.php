<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
echo json_encode([
    'barang' => (int) $pdo->query('SELECT COUNT(*) FROM barang')->fetchColumn(),
    'transaksi_hari_ini' => (int) $pdo->query('SELECT COUNT(*) FROM transaksi WHERE DATE(tanggal) = CURDATE()')->fetchColumn(),
    'omzet_hari_ini' => (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM transaksi WHERE DATE(tanggal) = CURDATE()')->fetchColumn(),
]);
