<?php $user = current_user(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<aside class="sidebar d-flex flex-column">
    <div class="brand mb-4 d-flex align-items-center gap-3">
        <span class="brand-mark bg-primary text-white d-flex justify-content-center align-items-center rounded shadow-sm" style="width: 45px; height: 45px; font-size: 1.5rem; font-weight: bold;">Q</span>
        <div class="brand-text">
            <strong class="d-block" style="font-size: 1.1rem; letter-spacing: 0.5px;">Quebec Store</strong>
            <small class="text-muted" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">Point of Sale</small>
        </div>
    </div>

    <?php if ($user): ?>
        <nav class="d-flex flex-column gap-2 flex-grow-1">
            <div class="nav-label text-muted small fw-bold mb-2 text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem;">Menu Utama</div>

            <?php if (($user['role'] ?? '') === 'kasir'): ?>
                <a href="<?= BASE_URL; ?>/dashboard_kasir.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2-fill"></i> <span>Dashboard Kasir</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL; ?>/dashboard.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span>
                </a>
            <?php endif; ?>

            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="<?= BASE_URL; ?>/produk/index.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/produk/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-box-seam-fill"></i> <span>Produk</span>
                </a>
                <a href="<?= BASE_URL; ?>/kategori/index.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/kategori/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-tags-fill"></i> <span>Kategori</span>
                </a>
                <a href="<?= BASE_URL; ?>/supplier/index.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/supplier/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-truck"></i> <span>Supplier</span>
                </a>
                <a href="<?= BASE_URL; ?>/penerimaan/index.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/penerimaan/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-box-arrow-in-down"></i> <span>Penerimaan Barang</span>
                </a>
            <?php endif; ?>



            <?php if (($user['role'] ?? '') === 'kasir'): ?>
                <a href="<?= BASE_URL; ?>/transaksi/index.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/transaksi/') !== false && strpos($_SERVER['REQUEST_URI'], 'jual.php') === false ? 'active' : '' ?>">
                    <i class="bi bi-receipt"></i> <span>Transaksi</span>
                </a>
            <?php endif; ?>

            <?php if (($user['role'] ?? '') === 'kasir'): ?>
                <a href="<?= BASE_URL; ?>/transaksi/jual.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'jual.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-cart-plus-fill"></i> <span>Kasir</span>
                </a>
            <?php endif; ?>

            <hr class="my-2 border-secondary opacity-25">
            <div class="nav-label text-muted small fw-bold mb-2 text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem;">Lainnya</div>

            <?php if (($user['role'] ?? '') === 'pemilik' || ($user['role'] ?? '') === 'admin'): ?>
                <a href="<?= BASE_URL; ?>/laporan/index.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/laporan/index.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart-fill"></i> <span>Laporan Penjualan</span>
                </a>
                <a href="<?= BASE_URL; ?>/laporan/pendapatan.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/laporan/pendapatan.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-wallet2"></i> <span>Laporan Pendapatan</span>
                </a>
                <a href="<?= BASE_URL; ?>/laporan/stok.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/laporan/stok.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-box2-fill"></i> <span>Laporan Stok</span>
                </a>
            <?php endif; ?>

            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="<?= BASE_URL; ?>/user/index.php" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/user/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i> <span>Manajemen User</span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="mt-auto pt-4 border-top border-secondary border-opacity-25">
            <div class="d-flex align-items-center mb-3 px-2">
                <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center text-white me-2" style="width: 35px; height: 35px; font-size: 0.9rem;">
                    <?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <strong class="d-block text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($user['nama'] ?? 'User') ?></strong>
                    <span class="badge bg-<?= ($user['role'] ?? '') === 'pemilik' ? 'primary' : 'secondary' ?> fw-normal" style="font-size: 0.7rem;"><?= ucfirst($user['role'] ?? '') ?></span>
                </div>
            </div>
            <a href="<?= BASE_URL; ?>/ganti_password.php" class="nav-link text-warning d-flex align-items-center gap-2 py-2 mb-1" style="transition: all 0.2s;">
                <i class="bi bi-key-fill"></i> <span>Ganti Password</span>
            </a>
            <a href="<?= BASE_URL; ?>/logout.php?role=<?= urlencode($user['role'] ?? '') ?>" class="nav-link text-danger d-flex align-items-center gap-2 py-2" style="transition: all 0.2s;" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
                <i class="bi bi-box-arrow-left"></i> <span>Keluar Sistem</span>
            </a>
        </div>
    <?php endif; ?>
</aside>