<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Laporan Transaksi';

// Ambil data dari view v_laporan_transaksi, urutkan dari yang terbaru
$stmt = $pdo->query("SELECT * FROM v_laporan_transaksi ORDER BY tanggal_jual DESC, transaksi_id DESC");
$laporan = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h2>Laporan Transaksi Penjualan</h2>
        <p class="muted">Detail seluruh transaksi penjualan barang dan informasi profit.</p>
    </div>
    <div class="card-body">
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal Jual</th>
                        <th>Kode Item</th>
                        <th>Merek</th>
                        <th>Kategori</th>
                        <th>Harga Jual</th>
                        <th>Keuntungan</th>
                        <th>Kasir</th>
                        <th>Metode Bayar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($laporan) > 0): ?>
                        <?php foreach ($laporan as $row): ?>
                            <tr>
                                <td>#<?= clean($row['transaksi_id']); ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_jual'])); ?></td>
                                <td><strong><?= clean($row['kode_item']); ?></strong></td>
                                <td><?= clean($row['merek']); ?></td>
                                <td><?= clean(ucwords(str_replace('_', ' ', $row['kategori']))); ?></td>
                                <td><?= rupiah($row['harga_jual']); ?></td>
                                <td style="color: #28a745; font-weight: bold;">
                                    + <?= rupiah($row['keuntungan']); ?>
                                </td>
                                <td><?= clean($row['nama_kasir']); ?></td>
                                <td><?= clean(strtoupper($row['metode_bayar'])); ?></td>
                                <td class="actions">
                                    <a href="transaksi/detail.php?id=<?= $row['transaksi_id'] ?>" class="button secondary" style="padding: 4px 8px; font-size: 0.85rem;">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">Belum ada data transaksi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>