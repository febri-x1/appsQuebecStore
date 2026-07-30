<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$user = current_user();
if (!in_array($user['role'] ?? '', ['admin', 'pemilik'])) {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk admin/pemilik.');
    redirect('dashboard.php');
}

$pageTitle = 'Penerimaan Barang';

// Filter tanggal
$filter_start = $_GET['start'] ?? date('Y-m-01');
$filter_end   = $_GET['end']   ?? date('Y-m-d');

$stmt = safeExecute($pdo, "
    SELECT 
        pb.id_penerimaan,
        pb.no_penerimaan,
        pb.tanggal_terima,
        pb.qty,
        pb.keterangan,
        p.kode_item,
        p.merek,
        kb.nama_kategori,
        p.ukuran,
        p.kondisi,
        u.nama AS nama_admin,
        s.nama_supplier
    FROM penerimaan_barang pb
    JOIN produk p ON p.id = pb.produk_id
    JOIN kategori_produk kb ON kb.id = p.kategori_id
    JOIN users u ON u.id = pb.admin_id
    LEFT JOIN suppliers s ON s.id = pb.supplier_id
    WHERE pb.tanggal_terima BETWEEN ? AND ?
    ORDER BY pb.tanggal_terima DESC, pb.id_penerimaan DESC
", [$filter_start, $filter_end]);
$penerimaan = $stmt->fetchAll();

$stmtTotal = safeExecute($pdo, "
    SELECT COUNT(*) as total_record, COALESCE(SUM(qty), 0) as total_qty
    FROM penerimaan_barang
    WHERE tanggal_terima BETWEEN ? AND ?
", [$filter_start, $filter_end]);
$totals = $stmtTotal->fetch();

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-box-arrow-in-down text-primary me-2"></i>Penerimaan Barang</h2>
            <p class="text-muted small mb-0">Riwayat penerimaan &amp; restok barang dari supplier.</p>
        </div>
        <a href="tambah.php" class="btn btn-primary px-4">
            <i class="bi bi-plus-circle me-1"></i> Tambah Penerimaan
        </a>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-primary border-4">
                <div class="card-body text-center py-4">
                    <h6 class="text-muted text-uppercase fw-bold small">Total Penerimaan</h6>
                    <h3 class="mb-0 text-primary"><?= number_format($totals['total_record'], 0, ',', '.') ?></h3>
                    <small class="text-muted">transaksi</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-success border-4">
                <div class="card-body text-center py-4">
                    <h6 class="text-muted text-uppercase fw-bold small">Total Item Diterima</h6>
                    <h3 class="mb-0 text-success"><?= number_format($totals['total_qty'], 0, ',', '.') ?></h3>
                    <small class="text-muted">item / pcs</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-info border-4">
                <div class="card-body text-center py-4">
                    <h6 class="text-muted text-uppercase fw-bold small">Periode</h6>
                    <h5 class="mb-0 text-info" style="font-size:1rem;"><?= date('d M Y', strtotime($filter_start)) ?></h5>
                    <small class="text-muted">s/d <?= date('d M Y', strtotime($filter_end)) ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body bg-light rounded">
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-4">
                    <label class="form-label text-muted fw-bold small">Dari Tanggal</label>
                    <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($filter_start) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted fw-bold small">Sampai Tanggal</label>
                    <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($filter_end) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i>Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:50px;">No</th>
                            <th>No. Penerimaan</th>
                            <th>Tanggal</th>
                            <th>Supplier</th>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th class="text-center">Qty</th>
                            <th>Keterangan</th>
                            <th>Admin</th>
                            <th class="text-center pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($penerimaan) > 0): $no = 1; foreach ($penerimaan as $p): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold" style="font-size:0.8rem; letter-spacing:0.5px;">
                                    <?= htmlspecialchars($p['no_penerimaan']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= date('d M Y', strtotime($p['tanggal_terima'])) ?></span>
                            </td>
                            <td>
                                <?php if ($p['nama_supplier']): ?>
                                <a href="<?= BASE_URL ?>/supplier/detail.php?id=<?= htmlspecialchars($p['nama_supplier'] ?? '') ?>" class="text-decoration-none">
                                    <i class="bi bi-truck text-primary me-1"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars($p['nama_supplier']) ?></span>
                                </a>
                                <?php else: ?>
                                <span class="text-muted fst-italic">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($p['merek']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['kode_item']) ?> &bull; <?= htmlspecialchars($p['ukuran']) ?> &bull; Kondisi <?= htmlspecialchars($p['kondisi']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size:0.78rem;">
                                    <?= htmlspecialchars($p['nama_kategori']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold" style="font-size:0.9rem; padding:6px 14px;">
                                    <?= number_format($p['qty'], 0, ',', '.') ?>
                                </span>
                            </td>
                            <td class="text-muted" style="max-width:200px; word-break:break-word;">
                                <?= $p['keterangan'] ? htmlspecialchars($p['keterangan']) : '<span class="text-muted fst-italic">—</span>' ?>
                            </td>
                            <td>
                                <i class="bi bi-person-circle text-secondary me-1"></i>
                                <?= htmlspecialchars($p['nama_admin']) ?>
                            </td>
                            <td class="text-center pe-4">
                                <a href="detail.php?id=<?= $p['id_penerimaan'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="hapus.php?id=<?= $p['id_penerimaan'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   title="Hapus"
                                   onclick="return confirm('Yakin ingin menghapus penerimaan ini?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                                Belum ada data penerimaan barang pada periode ini.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.php'; ?>
