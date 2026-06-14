<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php'; // hanya role 'pemilik'
require_once '../includes/functions.php';

// Pastikan hanya role pemilik yang bisa akses
if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk pemilik.');
    redirect('dashboard.php');
}

$pageTitle = 'Manajemen Barang';

// Setup pagination & filter
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$kondisi_filter = $_GET['kondisi'] ?? '';
$search = $_GET['search'] ?? '';

// Build query dinamis
$where = [];
$params = [];

if ($status_filter !== '') {
    $where[] = "status = ?";
    $params[] = $status_filter;
}
if ($kategori_filter !== '') {
    $where[] = "b.kategori_id = ?";
    $params[] = $kategori_filter;
}
if ($kondisi_filter !== '') {
    $where[] = "b.kondisi = ?";
    $params[] = $kondisi_filter;
}
if ($search !== '') {
    $where[] = "(b.merek LIKE ? OR b.kode_item LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Hitung total data
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM barang b $whereClause");
$stmtCount->execute($params);
$totalData = $stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Ambil data
$sql = "SELECT b.*, kb.nama_kategori AS kategori FROM barang b LEFT JOIN kategori_barang kb ON b.kategori_id = kb.id $whereClause ORDER BY b.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$barangList = $stmt->fetchAll();

// Ambil kategori untuk filter
$kategoriList = $pdo->query("SELECT id, nama_kategori FROM kategori_barang ORDER BY nama_kategori ASC")->fetchAll();

include '../includes/header.php';
?>

<!-- Inject Bootstrap 5 CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container-fluid mt-4" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Manajemen Barang</h2>
        <a href="tambah.php" class="btn btn-primary">+ Tambah Barang</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="di_rak" <?= $status_filter == 'di_rak' ? 'selected' : '' ?>>Di Rak</option>
                        <option value="terjual" <?= $status_filter == 'terjual' ? 'selected' : '' ?>>Terjual</option>
                        <option value="rusak" <?= $status_filter == 'rusak' ? 'selected' : '' ?>>Rusak</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat['id'] ?>" <?= $kategori_filter == $kat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kondisi</label>
                    <select name="kondisi" class="form-select">
                        <option value="">Semua Kondisi</option>
                        <option value="A" <?= $kondisi_filter == 'A' ? 'selected' : '' ?>>A</option>
                        <option value="B" <?= $kondisi_filter == 'B' ? 'selected' : '' ?>>B</option>
                        <option value="C" <?= $kondisi_filter == 'C' ? 'selected' : '' ?>>C</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cari</label>
                    <input type="text" name="search" class="form-control" placeholder="Merek atau Kode Item" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="text-muted">Menampilkan <?= count($barangList) ?> dari <?= $totalData ?> barang</p>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kode Item</th>
                            <th>Merek</th>
                            <th>Kategori</th>
                            <th>Ukuran</th>
                            <th>Kondisi</th>
                            <th>Modal</th>
                            <th>Harga Jual</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($barangList) > 0): ?>
                            <?php $no = $offset + 1;
                            foreach ($barangList as $b):
                                // Cek deadstock > 30 hari
                                $hari_di_rak = (strtotime(date('Y-m-d')) - strtotime($b['tanggal_masuk'])) / (60 * 60 * 24);
                                $is_deadstock = ($b['status'] == 'di_rak' && $hari_di_rak > 30);
                            ?>
                                <tr class="<?= $is_deadstock ? 'table-warning' : '' ?>">
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($b['kode_item']) ?></td>
                                    <td><?= htmlspecialchars($b['merek']) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $b['kategori'])) ?></td>
                                    <td><?= htmlspecialchars($b['ukuran']) ?></td>
                                    <td>
                                        <?php if ($b['kondisi'] == 'A'): ?>
                                            <span class="badge bg-success">A</span>
                                        <?php elseif ($b['kondisi'] == 'B'): ?>
                                            <span class="badge bg-warning text-dark">B</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">C</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatRupiah($b['modal']) ?></td>
                                    <td><?= formatRupiah($b['harga_jual']) ?></td>
                                    <td>
                                        <?php if ($b['status'] == 'di_rak'): ?>
                                            <span class="badge bg-primary">Di Rak</span>
                                        <?php elseif ($b['status'] == 'terjual'): ?>
                                            <span class="badge bg-secondary">Terjual</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rusak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detail.php?id=<?= $b['id'] ?>" class="btn btn-info text-white">Detail</a>
                                            <a href="edit.php?id=<?= $b['id'] ?>" class="btn btn-warning text-dark">Edit</a>
                                            <?php if ($b['status'] == 'terjual'): ?>
                                                <button class="btn btn-danger" disabled>Hapus</button>
                                            <?php else: ?>
                                                <button class="btn btn-danger" onclick="confirmDelete(<?= $b['id'] ?>)">Hapus</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">Data tidak ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&kategori=<?= urlencode($kategori_filter) ?>&kondisi=<?= urlencode($kondisi_filter) ?>&search=<?= urlencode($search) ?>">Sebelumnya</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&kategori=<?= urlencode($kategori_filter) ?>&kondisi=<?= urlencode($kondisi_filter) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&kategori=<?= urlencode($kategori_filter) ?>&kondisi=<?= urlencode($kondisi_filter) ?>&search=<?= urlencode($search) ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus barang ini? Data yang dihapus tidak dapat dikembalikan.
            </div>
            <div class="modal-footer">
                <form method="POST" action="hapus.php" id="deleteForm">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDelete(id) {
        document.getElementById('deleteId').value = id;
        var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        myModal.show();
    }
</script>

<?php include '../includes/footer.php'; ?>