<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$stmt = $pdo->prepare('SELECT t.*, u.nama AS kasir FROM transaksi t LEFT JOIN users u ON u.id = t.user_id WHERE DATE(t.tanggal) BETWEEN ? AND ? ORDER BY t.tanggal DESC');
$stmt->execute([$start, $end]);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
</head>
<body class="print-page">
    <h1>Laporan Penjualan</h1>
    <p>Periode <?= clean($start); ?> sampai <?= clean($end); ?></p>
    <table>
        <thead><tr><th>Invoice</th><th>Tanggal</th><th>Kasir</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr><td><?= clean($row['no_invoice']); ?></td><td><?= clean($row['tanggal']); ?></td><td><?= clean($row['kasir'] ?? '-'); ?></td><td><?= rupiah($row['total']); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <script>window.print();</script>
</body>
</html>
