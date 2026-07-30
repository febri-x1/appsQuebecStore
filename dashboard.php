<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect kasir ke dashboard khusus
if (current_user()['role'] === 'kasir') {
    redirect('dashboard_kasir.php');
}

$pageTitle = 'Dashboard Operasional';

// Metrics Data
$stokAktif = (int) $pdo->query("SELECT COUNT(*) FROM produk WHERE status = 'di_rak'")->fetchColumn();
$deadstock = (int) $pdo->query("SELECT COUNT(*) FROM v_deadstock")->fetchColumn();
$transaksiHariIni = (int) $pdo->query("SELECT COUNT(*) FROM transaksi WHERE tanggal_jual = CURDATE()")->fetchColumn();
$omzetHariIni = (float) $pdo->query("SELECT COALESCE(SUM(harga_jual), 0) FROM transaksi WHERE tanggal_jual = CURDATE()")->fetchColumn();
$labaHariIni = (float) $pdo->query("SELECT COALESCE(SUM(harga_jual - modal), 0) FROM transaksi WHERE tanggal_jual = CURDATE()")->fetchColumn();

// Recent Transactions Data (Real-time feed)
$stmtRecent = $pdo->query("
    SELECT t.id, t.tanggal_jual, b.kode_item, b.merek, t.harga_jual, t.metode_bayar, k.nama as nama_kasir, t.created_at 
    FROM transaksi t
    JOIN produk b ON t.produk_id = b.id
    LEFT JOIN users k ON t.kasir_id = k.id
    ORDER BY t.created_at DESC
    LIMIT 6
");
$recentTransactions = $stmtRecent->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Import Bootstrap & Icons for modern UI -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<!-- Custom minimal overrides to prevent conflict with global style.css -->
<style>
    .app-shell .main-content h1, .app-shell .main-content h2, .app-shell .main-content h3 {
        margin: 0;
    }
    /* Reset link colors inside cards */
    a.text-decoration-none:hover {
        opacity: 0.8;
    }
</style>

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold"><i class="bi bi-speedometer2 text-primary me-2"></i>Dashboard Harian</h2>
            <p class="text-muted mb-0 small">Ringkasan aktivitas dan performa toko hari ini.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark border p-2"><i class="bi bi-calendar-check me-2"></i><?= date('d M Y') ?></span>
        </div>
    </div>

    <!-- Quick Stats Cards (Hari Ini) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1" style="font-size: 0.8rem;">Omzet (Hari Ini)</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?= formatRupiah($omzetHariIni) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-wallet2 fs-2 text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1" style="font-size: 0.8rem;">Laba (Hari Ini)</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">+<?= formatRupiah($labaHariIni) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-cash-coin fs-2 text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-info border-4 h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1" style="font-size: 0.8rem;">Total Transaksi</div>
                            <div class="h5 mb-0 fw-bold text-gray-800"><?= $transaksiHariIni ?> <small class="text-muted fw-normal" style="font-size: 0.7rem;">Struk</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-receipt fs-2 text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-<?= $deadstock > 0 ? 'danger' : 'secondary' ?> border-4 h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-<?= $deadstock > 0 ? 'danger' : 'secondary' ?> text-uppercase mb-1" style="font-size: 0.8rem;">Deadstock / Stok</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?= $deadstock ?> <small class="text-muted fw-normal fs-6">/ <?= $stokAktif ?> Pcs</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-exclamation-triangle-fill fs-2 text-<?= $deadstock > 0 ? 'danger' : 'secondary' ?> opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Live Feed Transaksi -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white pt-3 pb-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-activity text-success me-2"></i> Transaksi Terbaru (Live Feed)</h6>
                    <a href="<?= BASE_URL ?>/laporan_transaksi.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Waktu</th>
                                    <th>Item & Merek</th>
                                    <th>Kasir</th>
                                    <th>Metode</th>
                                    <th class="text-end pe-4">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentTransactions) > 0): ?>
                                    <?php foreach ($recentTransactions as $tx): ?>
                                        <tr>
                                            <td class="ps-4 text-muted small"><?= date('H:i', strtotime($tx['created_at'])) ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($tx['kode_item']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($tx['merek']) ?></div>
                                            </td>
                                            <td><i class="bi bi-person-circle text-secondary me-1"></i><?= htmlspecialchars($tx['nama_kasir'] ?: 'Anonim') ?></td>
                                            <td>
                                                <?php if($tx['metode_bayar'] == 'tunai'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-cash me-1"></i>Tunai</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-credit-card me-1"></i><?= strtoupper($tx['metode_bayar']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-primary"><?= formatRupiah($tx['harga_jual']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            Belum ada transaksi hari ini.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Panel -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100 bg-light">
                <div class="card-header bg-transparent pt-3 pb-2 border-0">
                    <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> Aksi Cepat</h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-grid gap-3">
                        <a href="<?= BASE_URL ?>/produk/tambah.php" class="btn btn-primary d-flex align-items-center p-3 text-start shadow-sm hover-shadow transition">
                            <i class="bi bi-plus-circle fs-3 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Tambah Produk Baru</h6>
                                <small class="text-white-50">Input barang masuk ke gudang</small>
                            </div>
                        </a>
                        
                        <a href="<?= BASE_URL ?>/laporan/index.php" class="btn btn-info text-white d-flex align-items-center p-3 text-start shadow-sm hover-shadow transition">
                            <i class="bi bi-bar-chart-line fs-3 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Analisis Laporan</h6>
                                <small class="text-white-50">Buka grafik & rekap penjualan</small>
                            </div>
                        </a>

                        <a href="<?= BASE_URL ?>/deadstock.php" class="btn btn-outline-danger d-flex align-items-center p-3 text-start bg-white shadow-sm hover-shadow transition">
                            <i class="bi bi-exclamation-triangle fs-3 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Periksa Deadstock</h6>
                                <small class="text-muted">Cek <?= $deadstock ?> item menumpuk di rak</small>
                            </div>
                        </a>
                        
                        <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-outline-secondary d-flex align-items-center p-3 text-start bg-white shadow-sm hover-shadow transition">
                            <i class="bi bi-people fs-3 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Manajemen Kasir</h6>
                                <small class="text-muted">Kelola akun dan role pengguna</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>