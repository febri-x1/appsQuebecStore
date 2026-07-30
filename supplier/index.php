<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$user = current_user();
if (!in_array($user['role'] ?? '', ['admin', 'pemilik'])) {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk admin/pemilik.');
    redirect('dashboard.php');
}

$pageTitle = 'Manajemen Supplier';

// Pencarian
$search = trim($_GET['q'] ?? '');
$params = [];
$whereClause = '';
if ($search !== '') {
    $whereClause = "WHERE nama_supplier LIKE ? OR telepon LIKE ? OR alamat LIKE ?";
    $like = "%{$search}%";
    $params = [$like, $like, $like];
}

$suppliers = safeExecute($pdo, "
    SELECT s.*,
           COUNT(p.id) AS jumlah_produk
    FROM suppliers s
    LEFT JOIN produk p ON p.supplier_id = s.id
    {$whereClause}
    GROUP BY s.id
    ORDER BY s.nama_supplier ASC
", $params)->fetchAll();

$totalSupplier = count($suppliers);

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
    .supplier-avatar {
        width: 42px; height: 42px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 1.1rem; color: #fff;
        flex-shrink: 0;
    }
    .stat-card { border-left: 4px solid; border-radius: 8px; }
    .card-hover { transition: box-shadow 0.2s, transform 0.2s; }
    .card-hover:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.10); transform: translateY(-2px); }
    .search-bar { max-width: 360px; }
</style>

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="mb-0 fw-bold"><i class="bi bi-truck text-primary me-2"></i>Manajemen Supplier</h2>
            <p class="text-muted small mb-0">Kelola data pemasok bal pakaian thrift toko Anda.</p>
        </div>
        <a href="tambah.php" class="btn btn-primary px-4">
            <i class="bi bi-plus-circle me-1"></i> Tambah Supplier
        </a>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm stat-card border-primary">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="supplier-avatar bg-primary"><i class="bi bi-truck fs-5"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Supplier</div>
                        <div class="fw-bold fs-4"><?= $totalSupplier ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm stat-card border-success">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="supplier-avatar bg-success"><i class="bi bi-box-seam fs-5"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Produk Masuk</div>
                        <div class="fw-bold fs-4"><?= array_sum(array_column($suppliers, 'jumlah_produk')) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm stat-card border-warning">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="supplier-avatar bg-warning text-dark"><i class="bi bi-search fs-5"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Hasil Pencarian</div>
                        <div class="fw-bold fs-4"><?= $search !== '' ? $totalSupplier . ' ditemukan' : '—' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body bg-light rounded">
            <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
                <div class="flex-grow-1 search-bar">
                    <label class="form-label text-muted fw-bold small mb-1">Cari Supplier</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Nama, telepon, atau alamat..."
                               value="<?= htmlspecialchars($search) ?>" id="searchInput">
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Cari</button>
                    <?php if ($search !== ''): ?>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center pt-3 pb-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-list-ul text-primary me-2"></i>Daftar Supplier
                <span class="badge bg-primary-subtle text-primary ms-2"><?= $totalSupplier ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:50px;">No</th>
                            <th>Supplier</th>
                            <th>Telepon</th>
                            <th>Alamat</th>
                            <th class="text-end">Harga/Bal</th>
                            <th class="text-center">Isi/Bal</th>
                            <th class="text-end">Modal/Item</th>
                            <th class="text-center">Produk</th>
                            <th class="text-center pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($suppliers) > 0): $no = 1; foreach ($suppliers as $s): ?>
                        <?php
                            $colors = ['#0f766e','#0369a1','#7c3aed','#b45309','#be123c','#047857'];
                            $color  = $colors[($s['id'] - 1) % count($colors)];
                            $initial = mb_strtoupper(mb_substr($s['nama_supplier'], 0, 1));
                        ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="supplier-avatar" style="background:<?= $color ?>; width:36px; height:36px; font-size:0.95rem;">
                                        <?= $initial ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($s['nama_supplier']) ?></div>
                                        <?php if ($s['keterangan']): ?>
                                        <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($s['keterangan'], 0, 50, '...')) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($s['telepon']): ?>
                                <a href="tel:<?= htmlspecialchars($s['telepon']) ?>" class="text-decoration-none">
                                    <i class="bi bi-telephone text-success me-1"></i><?= htmlspecialchars($s['telepon']) ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted fst-italic">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted" style="max-width:200px;">
                                <?= $s['alamat'] ? htmlspecialchars(mb_strimwidth($s['alamat'], 0, 60, '...')) : '<span class="fst-italic">—</span>' ?>
                            </td>
                            <td class="text-end fw-semibold text-primary"><?= formatRupiah($s['harga_per_bal']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold"><?= number_format($s['isi_per_bal'], 0, ',', '.') ?> pcs</span>
                            </td>
                            <td class="text-end fw-semibold text-success"><?= formatRupiah($s['modal_per_item']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $s['jumlah_produk'] > 0 ? 'success' : 'secondary' ?>-subtle
                                             text-<?= $s['jumlah_produk'] > 0 ? 'success' : 'secondary' ?>
                                             border border-<?= $s['jumlah_produk'] > 0 ? 'success' : 'secondary' ?>-subtle fw-bold px-3">
                                    <?= $s['jumlah_produk'] ?>
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="detail.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus"
                                            onclick="confirmHapus(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nama_supplier'])) ?>', <?= $s['jumlah_produk'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                                <?= $search !== '' ? "Tidak ada supplier dengan kata kunci \"<strong>{$search}</strong>\"." : 'Belum ada data supplier.' ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="hapusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4" id="hapusModalBody">
                Apakah Anda yakin ingin menghapus supplier ini?
            </div>
            <div class="modal-footer border-0">
                <form method="POST" action="hapus.php" id="hapusForm">
                    <input type="hidden" name="id" id="hapusId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" id="hapusBtn">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmHapus(id, nama, jumlahProduk) {
    document.getElementById('hapusId').value = id;
    const body = document.getElementById('hapusModalBody');
    const btn  = document.getElementById('hapusBtn');
    if (jumlahProduk > 0) {
        body.innerHTML = `<div class="alert alert-warning border-0 mb-0">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Supplier <strong>${nama}</strong> tidak dapat dihapus karena masih memiliki <strong>${jumlahProduk} produk</strong> terkait.
            Hapus atau pindahkan produk terlebih dahulu.
        </div>`;
        btn.disabled = true;
        btn.classList.add('d-none');
    } else {
        body.innerHTML = `Apakah Anda yakin ingin menghapus supplier <strong>${nama}</strong>? Tindakan ini tidak dapat dibatalkan.`;
        btn.disabled = false;
        btn.classList.remove('d-none');
    }
    new bootstrap.Modal(document.getElementById('hapusModal')).show();
}
</script>
<?php include '../includes/footer.php'; ?>
