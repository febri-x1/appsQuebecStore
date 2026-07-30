<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Laporan Deadstock';
$stmt = $pdo->query("SELECT * FROM v_deadstock");
$items = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h2>Daftar Produk Deadstock (> 30 Hari)</h2>
        <p class="muted">Produk-produk ini sudah berada di rak lebih dari 30 hari dan belum terjual. Pertimbangkan untuk memberi diskon atau promo.</p>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kode Item</th>
                    <th>Merek</th>
                    <th>Kategori</th>
                    <th>Kondisi</th>
                    <th>Modal</th>
                    <th>Harga Jual</th>
                    <th>Hari di Rak</th>
                    <th>Supplier</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= clean($item['kode_item']); ?></strong></td>
                            <td><?= clean($item['merek']); ?></td>
                            <td><?= clean(ucwords(str_replace('_', ' ', $item['kategori']))); ?></td>
                            <td><?= clean($item['kondisi']); ?></td>
                            <td><?= rupiah($item['modal']); ?></td>
                            <td><?= rupiah($item['harga_jual']); ?></td>
                            <td style="color: #dc3545; font-weight: bold;"><?= clean($item['hari_di_rak']); ?> hari</td>
                            <td><?= clean($item['nama_supplier']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">Tidak ada produk deadstock saat ini. Bagus!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>