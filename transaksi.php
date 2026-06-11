<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Restrict access to kasir only
if (current_user()['role'] !== 'kasir') {
    flash('error', 'Hanya kasir yang diizinkan untuk melakukan proses transaksi.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_item = trim($_POST['kode_item'] ?? '');
    $metode_bayar = $_POST['metode_bayar'] ?? 'tunai';
    $catatan = trim($_POST['catatan'] ?? '');

    if ($kode_item) {
        // Cari barang berdasarkan kode_item
        $stmt = $pdo->prepare("SELECT id, harga_jual, modal, merek, kategori, status FROM barang WHERE kode_item = ?");
        $stmt->execute([$kode_item]);
        $barang = $stmt->fetch();

        if ($barang) {
            if ($barang['status'] === 'di_rak') {
                try {
                    $pdo->beginTransaction();

                    // 1. Catat transaksi penjualan
                    $stmtTrans = $pdo->prepare("
                        INSERT INTO transaksi (barang_id, kasir_id, harga_jual, modal, metode_bayar, catatan, tanggal_jual)
                        VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                    ");
                    $stmtTrans->execute([
                        $barang['id'],
                        current_user()['id'],
                        $barang['harga_jual'],
                        $barang['modal'],
                        $metode_bayar,
                        $catatan
                    ]);

                    // 2. Ubah status barang menjadi 'terjual'
                    $stmtUpdate = $pdo->prepare("UPDATE barang SET status = 'terjual' WHERE id = ?");
                    $stmtUpdate->execute([$barang['id']]);

                    $pdo->commit();
                    flash('success', 'Transaksi berhasil: ' . $barang['merek'] . ' (' . ucwords(str_replace('_', ' ', $barang['kategori'])) . ') laku terjual.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
                }
            } else {
                flash('error', 'Transaksi gagal: Barang dengan kode "' . clean($kode_item) . '" tidak dapat dijual karena berstatus "' . clean($barang['status']) . '".');
            }
        } else {
            flash('error', 'Item dengan kode "' . clean($kode_item) . '" tidak ditemukan di database.');
        }
    } else {
        flash('error', 'Kode item wajib diisi.');
    }

    redirect('transaksi.php');
}

$pageTitle = 'Kasir / Transaksi Baru';

// Ambil 5 transaksi terakhir untuk ditampilkan di layar
$stmtRecent = $pdo->query("
    SELECT t.id, b.kode_item, b.merek, t.harga_jual, t.metode_bayar, t.created_at
    FROM transaksi t
    JOIN barang b ON t.barang_id = b.id
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recentTransactions = $stmtRecent->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h2>Proses Penjualan</h2>
        <p class="muted">Masukkan atau scan barcode kode item untuk memproses transaksi.</p>
    </div>
    <div class="card-body">
        <form method="post" action="transaksi.php" style="max-width: 500px;">
            <div style="margin-bottom: 1rem;">
                <label for="kode_item" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Kode Item / Barcode</label>
                <input type="text" id="kode_item" name="kode_item" required autofocus placeholder="Contoh: QS-2025-00001" style="width: 100%; padding: 0.6rem; font-size: 1.1rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label for="metode_bayar" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Metode Pembayaran</label>
                <select id="metode_bayar" name="metode_bayar" required style="width: 100%; padding: 0.6rem; font-size: 1.1rem; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="tunai">Tunai</option>
                    <option value="qris">QRIS</option>
                    <option value="transfer">Transfer Bank</option>
                </select>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label for="catatan" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Catatan (Opsional)</label>
                <input type="text" id="catatan" name="catatan" placeholder="Opsional: catatan transaksi..." style="width: 100%; padding: 0.6rem; font-size: 1.1rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <button type="submit" style="padding: 0.75rem 1.5rem; font-size: 1.1rem; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; width: 100%;">
                Selesaikan Transaksi
            </button>
        </form>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h2>5 Transaksi Terakhir</h2>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Kode Item</th>
                    <th>Merek</th>
                    <th>Harga Jual</th>
                    <th>Metode Bayar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentTransactions) > 0): ?>
                    <?php foreach ($recentTransactions as $tx): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($tx['created_at'])); ?></td>
                            <td><strong><?= clean($tx['kode_item']); ?></strong></td>
                            <td><?= clean($tx['merek']); ?></td>
                            <td><?= rupiah($tx['harga_jual']); ?></td>
                            <td><?= clean(strtoupper($tx['metode_bayar'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Belum ada transaksi yang tercatat hari ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>