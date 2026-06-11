<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect kasir ke dashboard khusus
if (current_user()['role'] === 'kasir') {
    redirect('dashboard_kasir.php');
}

$pageTitle = 'Dashboard';
$stokAktif = (int) $pdo->query("SELECT COUNT(*) FROM barang WHERE status = 'di_rak'")->fetchColumn();
$deadstock = (int) $pdo->query("SELECT COUNT(*) FROM v_deadstock")->fetchColumn();
$transaksiHariIni = (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE tanggal_jual = CURDATE()")->fetchColumn();
$omzetHariIni = (float) $pdo->query("SELECT COALESCE(SUM(harga_jual), 0) FROM transaksi WHERE tanggal_jual = CURDATE()")->fetchColumn();

include __DIR__ . '/includes/header.php';
?>
<section class="stats-grid">
    <article><span>Stok Aktif</span><strong><?= $stokAktif; ?></strong></article>
    <article>
        <a href="deadstock.php" style="text-decoration: none; color: inherit; display: block;">
            <span>Deadstock (> 30 Hari) ↗</span>
            <strong style="<?= $deadstock > 0 ? 'color: #dc3545;' : ''; ?>"><?= $deadstock; ?></strong>
        </a>
    </article>
    <article><span>Transaksi Hari Ini</span><strong><?= $transaksiHariIni; ?></strong></article>
    <article><span>Omzet Hari Ini</span><strong><?= rupiah($omzetHariIni); ?></strong></article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>