<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$user = current_user();
if (!in_array($user['role'] ?? '', ['admin', 'pemilik'])) {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk admin/pemilik.');
    redirect('dashboard.php');
}

$pageTitle = 'Laporan Stok Barang';

// 1. Dapatkan ringkasan total stok saat ini (status = di_rak)
$summary = safeExecute($pdo, "
    SELECT 
        COUNT(id) as total_item,
        SUM(modal) as total_modal,
        SUM(harga_jual) as total_potensi_pendapatan
    FROM produk 
    WHERE status = 'di_rak'
", [])->fetch();

// 2. Breakdown per Kategori
$stokKategori = safeExecute($pdo, "
    SELECT 
        COALESCE(kb.nama_kategori, 'Tanpa Kategori') as kategori,
        COUNT(p.id) as jumlah,
        SUM(p.modal) as modal,
        SUM(p.harga_jual) as potensi
    FROM produk p
    LEFT JOIN kategori_produk kb ON kb.id = p.kategori_id
    WHERE p.status = 'di_rak'
    GROUP BY p.kategori_id
    ORDER BY jumlah DESC
", [])->fetchAll();

// 3. Breakdown per Kondisi
$stokKondisi = safeExecute($pdo, "
    SELECT 
        kondisi,
        COUNT(id) as jumlah
    FROM produk 
    WHERE status = 'di_rak'
    GROUP BY kondisi
    ORDER BY kondisi ASC
", [])->fetchAll();

// 4. Detail Stok (dengan filter pencarian opsional)
$search = trim($_GET['q'] ?? '');
$params = ['di_rak'];
$whereClause = "WHERE p.status = ?";

if ($search !== '') {
    $whereClause .= " AND (p.kode_item LIKE ? OR p.merek LIKE ? OR kb.nama_kategori LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

$detailStok = safeExecute($pdo, "
    SELECT 
        p.kode_item, p.merek, kb.nama_kategori, p.ukuran, p.kondisi, p.modal, p.harga_jual, p.tanggal_masuk,
        s.nama_supplier
    FROM produk p
    LEFT JOIN kategori_produk kb ON kb.id = p.kategori_id
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    {$whereClause}
    ORDER BY p.tanggal_masuk DESC, p.id DESC
", $params)->fetchAll();

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    .kpi-card { border-left: 4px solid; }
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
</style>

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-box-seam text-primary me-2"></i>Laporan Stok</h2>
            <p class="text-muted small mb-0">Ringkasan inventaris produk yang saat ini berada di rak (siap jual).</p>
        </div>
        <button onclick="window.print()" class="btn btn-outline-secondary px-4">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
    </div>

    <!-- KPI Utama -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm kpi-card border-primary h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-1">Total Item Tersedia</div>
                    <h3 class="fw-bold text-primary mb-0"><?= number_format($summary['total_item'] ?? 0, 0, ',', '.') ?> <small class="fs-6 text-muted fw-normal">pcs</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm kpi-card border-warning h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-1">Total Nilai Modal</div>
                    <h3 class="fw-bold text-warning mb-0"><?= formatRupiah($summary['total_modal'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm kpi-card border-success h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-1">Estimasi Potensi Penjualan</div>
                    <h3 class="fw-bold text-success mb-0"><?= formatRupiah($summary['total_potensi_pendapatan'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Breakdown Kategori -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pt-3 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-tags text-primary me-2"></i>Stok Berdasarkan Kategori</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Kategori</th>
                                    <th class="text-center">Jumlah (Pcs)</th>
                                    <th class="text-end">Nilai Modal</th>
                                    <th class="text-end pe-3">Potensi Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($stokKategori) > 0): foreach ($stokKategori as $k): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold"><?= htmlspecialchars($k['kategori']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2"><?= $k['jumlah'] ?></span>
                                    </td>
                                    <td class="text-end text-muted"><?= formatRupiah($k['modal']) ?></td>
                                    <td class="text-end pe-3 fw-semibold text-success"><?= formatRupiah($k['potensi']) ?></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada data.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breakdown Kondisi -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pt-3 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-star-half text-primary me-2"></i>Stok Berdasarkan Kondisi</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <?php 
                    $kondisiMap = ['A' => 'success', 'B' => 'warning', 'C' => 'secondary'];
                    if (count($stokKondisi) > 0): 
                        foreach ($stokKondisi as $ko): 
                            $bg = $kondisiMap[$ko['kondisi']] ?? 'secondary';
                            $pct = ($summary['total_item'] > 0) ? round(($ko['jumlah'] / $summary['total_item']) * 100) : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1 small fw-bold">
                            <span>Kondisi <?= htmlspecialchars($ko['kondisi']) ?></span>
                            <span><?= $ko['jumlah'] ?> pcs (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-<?= $bg ?>" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="text-center text-muted">Tidak ada data.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Rincian -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-3 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-list-ul text-primary me-2"></i>Rincian Produk di Rak
                <span class="badge bg-secondary ms-2"><?= count($detailStok) ?> item</span>
            </h6>
            <form method="GET" class="d-flex gap-2">
                <div class="input-group input-group-sm" style="width:250px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari barang..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                <?php if ($search !== ''): ?>
                <a href="stok.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:0.9rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:40px;">No</th>
                            <th>Kode</th>
                            <th>Merek</th>
                            <th>Kategori</th>
                            <th class="text-center">Ukuran</th>
                            <th class="text-center">Kondisi</th>
                            <th>Supplier</th>
                            <th class="text-end">Modal</th>
                            <th class="text-end pe-3">Harga Jual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($detailStok) > 0): $no = 1; foreach ($detailStok as $d): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $no++ ?></td>
                            <td><span class="fw-bold text-primary"><?= htmlspecialchars($d['kode_item']) ?></span></td>
                            <td class="fw-semibold"><?= htmlspecialchars($d['merek']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($d['nama_kategori'] ?? '-') ?></td>
                            <td class="text-center"><?= htmlspecialchars($d['ukuran']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $kondisiMap[$d['kondisi']] ?? 'secondary' ?>-subtle text-<?= $kondisiMap[$d['kondisi']] ?? 'secondary' ?> border border-<?= $kondisiMap[$d['kondisi']] ?? 'secondary' ?>-subtle">
                                    <?= htmlspecialchars($d['kondisi']) ?>
                                </span>
                            </td>
                            <td class="text-muted"><small><?= htmlspecialchars($d['nama_supplier'] ?? '-') ?></small></td>
                            <td class="text-end text-muted"><?= formatRupiah($d['modal']) ?></td>
                            <td class="text-end pe-3 fw-bold text-success"><?= formatRupiah($d['harga_jual']) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">Barang tidak ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.php'; ?>
