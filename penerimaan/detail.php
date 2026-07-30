<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$user = current_user();
if (!in_array($user['role'] ?? '', ['admin', 'pemilik'])) {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk admin/pemilik.');
    redirect('dashboard.php');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'ID penerimaan tidak valid.');
    redirect('penerimaan/index.php');
}

$stmt = safeExecute($pdo, "
    SELECT 
        pb.id_penerimaan,
        pb.no_penerimaan,
        pb.tanggal_terima,
        pb.qty,
        pb.keterangan,
        pb.created_at,
        p.id AS produk_id,
        p.kode_item,
        p.merek,
        p.ukuran,
        p.kondisi,
        p.modal,
        p.harga_jual,
        p.status AS status_produk,
        kb.nama_kategori,
        s.nama_supplier,
        u.nama AS nama_admin,
        u.role AS role_admin
    FROM penerimaan_barang pb
    JOIN produk p ON p.id = pb.produk_id
    JOIN kategori_produk kb ON kb.id = p.kategori_id
    LEFT JOIN suppliers s ON s.id = pb.supplier_id
    JOIN users u ON u.id = pb.admin_id
    WHERE pb.id_penerimaan = ?
", [$id]);
$data = $stmt->fetch();

if (!$data) {
    flash('error', 'Data penerimaan tidak ditemukan.');
    redirect('penerimaan/index.php');
}

$pageTitle = 'Detail Penerimaan Barang';
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif; max-width: 860px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-box-arrow-in-down text-primary me-2"></i>Detail Penerimaan</h2>
            <p class="text-muted small mb-0">Informasi lengkap catatan penerimaan barang.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
            <a href="hapus.php?id=<?= $data['id_penerimaan'] ?>"
               class="btn btn-outline-danger"
               onclick="return confirm('Yakin ingin menghapus data penerimaan ini?');">
                <i class="bi bi-trash me-1"></i>Hapus
            </a>
        </div>
    </div>

    <!-- Header Card -->
    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); color: white;">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="small opacity-75 mb-1">Nomor Penerimaan</div>
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($data['no_penerimaan']) ?></h3>
                    <div class="opacity-75"><i class="bi bi-calendar3 me-1"></i><?= date('l, d F Y', strtotime($data['tanggal_terima'])) ?></div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="small opacity-75 mb-1">Total Item Diterima</div>
                    <h1 class="fw-bold mb-0"><?= number_format($data['qty'], 0, ',', '.') ?></h1>
                    <div class="small opacity-75">pcs / item</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Info Produk -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pt-3 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-box-seam text-primary me-2"></i>Informasi Produk</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted fw-semibold small" style="width:40%;">Kode Item</td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold"><?= htmlspecialchars($data['kode_item']) ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Nama Produk</td>
                            <td class="fw-bold"><?= htmlspecialchars($data['merek']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Kategori</td>
                            <td><?= htmlspecialchars($data['nama_kategori']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Ukuran</td>
                            <td><?= htmlspecialchars($data['ukuran']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Kondisi</td>
                            <td>
                                <span class="badge bg-<?= $data['kondisi'] === 'A' ? 'success' : ($data['kondisi'] === 'B' ? 'warning text-dark' : 'secondary') ?>">
                                    Kondisi <?= htmlspecialchars($data['kondisi']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Supplier</td>
                            <td><?= htmlspecialchars($data['nama_supplier']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Status Produk</td>
                            <td>
                                <?php
                                $badgeMap = ['di_rak' => 'success', 'terjual' => 'primary', 'rusak' => 'danger'];
                                $statusLabel = ['di_rak' => 'Di Rak', 'terjual' => 'Terjual', 'rusak' => 'Rusak'];
                                $sc = $badgeMap[$data['status_produk']] ?? 'secondary';
                                $sl = $statusLabel[$data['status_produk']] ?? $data['status_produk'];
                                ?>
                                <span class="badge bg-<?= $sc ?>"><?= $sl ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Harga Jual</td>
                            <td class="text-success fw-bold"><?= formatRupiah($data['harga_jual']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Info Penerimaan -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom pt-3 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clipboard-check text-primary me-2"></i>Detail Penerimaan</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted fw-semibold small">ID Penerimaan</td>
                            <td class="fw-bold">#<?= $data['id_penerimaan'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Tanggal Terima</td>
                            <td><?= date('d M Y', strtotime($data['tanggal_terima'])) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Jumlah Qty</td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold fs-6 px-3">
                                    <?= number_format($data['qty'], 0, ',', '.') ?> pcs
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Dicatat Oleh</td>
                            <td>
                                <i class="bi bi-person-circle text-secondary me-1"></i>
                                <?= htmlspecialchars($data['nama_admin']) ?>
                                <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:0.7rem;"><?= ucfirst($data['role_admin']) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold small">Dibuat Pada</td>
                            <td class="text-muted small"><?= date('d M Y H:i', strtotime($data['created_at'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Keterangan -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom pt-3 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-chat-text text-primary me-2"></i>Keterangan</h6>
                </div>
                <div class="card-body">
                    <?php if ($data['keterangan']): ?>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($data['keterangan'])) ?></p>
                    <?php else: ?>
                    <p class="text-muted fst-italic mb-0">Tidak ada keterangan.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.php'; ?>
