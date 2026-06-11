<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Laporan Penjualan';
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$stmt = $pdo->prepare('SELECT * FROM v_laporan_transaksi WHERE tanggal_jual BETWEEN ? AND ? ORDER BY tanggal_jual DESC, transaksi_id DESC');
$stmt->execute([$start, $end]);
$transactions = $stmt->fetchAll();
$total = array_sum(array_column($transactions, 'harga_jual'));
include __DIR__ . '/../includes/header.php';
?>
<form class="filter-bar" method="get">
    <label>Dari<input type="date" name="start" value="<?= clean($start); ?>"></label>
    <label>Sampai<input type="date" name="end" value="<?= clean($end); ?>"></label>
    <button type="submit">Tampilkan</button>
    <a class="button secondary" href="export_excel.php?start=<?= clean($start); ?>&end=<?= clean($end); ?>">Export Excel</a>
    <a class="button secondary" href="export_pdf.php?start=<?= clean($start); ?>&end=<?= clean($end); ?>">Export PDF</a>
</form>
<p class="report-total">Total omzet: <strong><?= rupiah($total); ?></strong></p>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID Trx</th>
                <th>Tanggal</th>
                <th>Item</th>
                <th>Kasir</th>
                <th>Harga Jual</th>
                <th>Keuntungan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $trx): ?>
                <tr>
                    <td>#<?= clean($trx['transaksi_id']); ?></td>
                    <td><?= date('d/m/Y', strtotime($trx['tanggal_jual'])); ?></td>
                    <td><?= clean($trx['kode_item'] . ' - ' . $trx['merek']); ?></td>
                    <td><?= clean($trx['nama_kasir'] ?? '-'); ?></td>
                    <td><?= rupiah($trx['harga_jual']); ?></td>
                    <td style="color: #28a745;">+ <?= rupiah($trx['keuntungan']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>