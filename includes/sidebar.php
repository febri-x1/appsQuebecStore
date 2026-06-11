<?php $user = current_user(); ?>
<aside class="sidebar">
    <div class="brand">
        <span class="brand-mark">Q</span>
        <div>
            <strong>Quebec Store</strong>
            <small>Point of Sale</small>
        </div>
    </div>
    <?php if ($user): ?>
        <nav>
            <?php if (($user['role'] ?? '') === 'kasir'): ?>
                <a href="<?= BASE_URL; ?>/dashboard_kasir.php">Dashboard Kasir</a>
            <?php else: ?>
                <a href="<?= BASE_URL; ?>/dashboard.php">Dashboard</a>
            <?php endif; ?>
            <?php if (($user['role'] ?? '') !== 'kasir'): ?>
                <a href="<?= BASE_URL; ?>/barang/index.php">Barang</a>
                <a href="<?= BASE_URL; ?>/transaksi/index.php">Transaksi</a>
            <?php endif; ?>
            <?php if (($user['role'] ?? '') === 'kasir'): ?>
                <a href="<?= BASE_URL; ?>/transaksi/jual.php">Kasir</a>
                <a href="<?= BASE_URL; ?>/katalog_kasir.php">Katalog Produk</a>
            <?php endif; ?>
            <a href="<?= BASE_URL; ?>/laporan/index.php">Laporan</a>
            <?php if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'pemilik'): ?>
                <a href="<?= BASE_URL; ?>/user/index.php">User</a>
            <?php endif; ?>
        </nav>
        <div style="margin-top: auto; padding-top: 20px;">
             <a href="<?= BASE_URL; ?>/logout.php?role=<?= urlencode($user['role'] ?? '') ?>" style="color: #ff6b6b;"><i class="bi bi-box-arrow-left"></i> Keluar (<?= ucfirst($user['role'] ?? '') ?>)</a>
        </div>
    <?php endif; ?>
</aside>
