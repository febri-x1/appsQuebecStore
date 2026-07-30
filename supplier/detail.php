<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$user = current_user();
if (!in_array($user['role'] ?? '', ['admin', 'pemilik'])) {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'ID tidak valid.'); redirect('supplier/index.php'); }

$supplier = safeExecute($pdo, "
    SELECT s.*, COUNT(p.id) AS jumlah_produk
    FROM suppliers s
    LEFT JOIN produk p ON p.supplier_id = s.id
    WHERE s.id = ?
    GROUP BY s.id
", [$id])->fetch();

if (!$supplier) { flash('error', 'Supplier tidak ditemukan.'); redirect('supplier/index.php'); }

// Ambil produk terkait
$produkList = safeExecute($pdo, "
    SELECT p.id, p.kode_item, p.merek, kb.nama_kategori, p.ukuran, p.kondisi, p.status, p.harga_jual, p.modal
    FROM produk p
    JOIN kategori_produk kb ON kb.id = p.kategori_id
    WHERE p.supplier_id = ?
    ORDER BY p.tanggal_masuk DESC
", [$id])->fetchAll();

$pageTitle = 'Detail Supplier — ' . $supplier['nama_supplier'];
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif; max-width: 1100px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="bi bi-truck text-primary me-2"></i>Detail Supplier</h2>
            <p class="text-muted small mb-0">Informasi lengkap dan daftar produk dari supplier ini.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="edit.php?id=<?= $id ?>" class="btn btn-warning fw-semibold"><i class="bi bi-pencil me-1"></i>Edit</a>
            <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>
    </div>

    <!-- Header Card -->
    <?php
    $colors = ['#0f766e','#0369a1','#7c3aed','#b45309','#be123c','#047857'];
    $color  = $colors[($supplier['id'] - 1) % count($colors)];
    $initial = mb_strtoupper(mb_substr($supplier['nama_supplier'], 0, 1));
    ?>
    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, <?= $color ?> 0%, <?= $color ?>cc 100%); color:white;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-4">
                <div style="width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.2);
                             display:flex;align-items:center;justify-content:center;
                             font-size:2rem;font-weight:700;flex-shrink:0;">
                    <?= $initial ?>
                </div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($supplier['nama_supplier']) ?></h3>
                    <div class="opacity-75 d-flex flex-wrap gap-3">
                        <?php if ($supplier['telepon']): ?>
                        <span><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($supplier['telepon']) ?></span>
                        <?php endif; ?>
                        <?php if ($supplier['alamat']): ?>
                        <span><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($supplier['alamat']) ?></span>
                        <?php endif; ?>
                        <span><i class="bi bi-calendar3 me-1"></i>Terdaftar <?= date('d M Y', strtotime($supplier['created_at'])) ?></span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="opacity-75 small">Total Produk</div>
                    <div class="fw-bold display-6"><?= $supplier['jumlah_produk'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Info Kalkulasi -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pt-3 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-calculator text-primary me-2"></i>Kalkulasi Modal</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted fw-semibold small">Harga per Bal</td>
                            <td class="fw-bold text-primary fs-5"><?= formatRupiah($supplier['harga_per_bal']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Isi per Bal</td>
                            <td class="fw-bold"><?= number_format($supplier['isi_per_bal'], 0, ',', '.') ?> pcs</td>
                        </tr>
                        <tr class="table-success">
                            <td class="text-muted fw-semibold small">Modal per Item</td>
                            <td class="fw-bold text-success fs-5"><?= formatRupiah($supplier['modal_per_item'] ?? 0) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- KPI Produk -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pt-3 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart text-primary me-2"></i>Statistik Produk</h6>
                </div>
                <div class="card-body">
                    <?php
                    $diRak   = count(array_filter($produkList, fn($p) => $p['status'] === 'di_rak'));
                    $terjual = count(array_filter($produkList, fn($p) => $p['status'] === 'terjual'));
                    $rusak   = count(array_filter($produkList, fn($p) => $p['status'] === 'rusak'));
                    ?>
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="p-3 rounded" style="background:#f0fdf4;">
                                <div class="fw-bold fs-3 text-success"><?= $diRak ?></div>
                                <div class="text-muted small">Di Rak</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded" style="background:#eff6ff;">
                                <div class="fw-bold fs-3 text-primary"><?= $terjual ?></div>
                                <div class="text-muted small">Terjual</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded" style="background:#fff1f2;">
                                <div class="fw-bold fs-3 text-danger"><?= $rusak ?></div>
                                <div class="text-muted small">Rusak</div>
                            </div>
                        </div>
                    </div>
                    <?php if ($supplier['keterangan']): ?>
                    <div class="mt-3 text-muted small border-top pt-3">
                        <strong>Catatan:</strong> <?= htmlspecialchars($supplier['keterangan']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Produk Terkait -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-3 pb-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-boxes text-primary me-2"></i>Daftar Produk dari Supplier Ini
                <span class="badge bg-primary-subtle text-primary ms-2"><?= count($produkList) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Kode Item</th>
                            <th>Merek</th>
                            <th>Kategori</th>
                            <th class="text-center">Ukuran</th>
                            <th class="text-center">Kondisi</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Modal</th>
                            <th class="text-end pe-4">Harga Jual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($produkList) > 0): foreach ($produkList as $p): ?>
                        <?php
                            $statusMap  = ['di_rak' => ['success', 'Di Rak'], 'terjual' => ['primary', 'Terjual'], 'rusak' => ['danger', 'Rusak']];
                            [$sc, $sl]  = $statusMap[$p['status']] ?? ['secondary', $p['status']];
                            $kondisiMap = ['A' => 'success', 'B' => 'warning', 'C' => 'secondary'];
                        ?>
                        <tr>
                            <td class="ps-4">
                                <a href="<?= BASE_URL ?>/produk/detail.php?id=<?= $p['id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= htmlspecialchars($p['kode_item']) ?>
                                </a>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($p['merek']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($p['nama_kategori']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($p['ukuran']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $kondisiMap[$p['kondisi']] ?? 'secondary' ?>-subtle text-<?= $kondisiMap[$p['kondisi']] ?? 'secondary' ?> border border-<?= $kondisiMap[$p['kondisi']] ?? 'secondary' ?>-subtle">
                                    <?= $p['kondisi'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle"><?= $sl ?></span>
                            </td>
                            <td class="text-end text-muted"><?= formatRupiah($p['modal']) ?></td>
                            <td class="text-end pe-4 fw-bold text-success"><?= formatRupiah($p['harga_jual']) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                                Belum ada produk dari supplier ini.
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
