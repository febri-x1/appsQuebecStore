<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php'; // hanya role 'pemilik'
require_once '../includes/functions.php';

// Pastikan hanya role pemilik yang bisa akses
if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk pemilik.');
    redirect('dashboard.php');
}

$pageTitle = 'Manajemen Produk';

// Setup pagination & filter
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$kondisi_filter = $_GET['kondisi'] ?? '';
$min_harga = $_GET['min_harga'] ?? '';
$max_harga = $_GET['max_harga'] ?? '';
$ukuran_filter = $_GET['ukuran'] ?? '';
$warna_filter = $_GET['warna'] ?? '';
$sumber_filter = $_GET['sumber'] ?? '';
$search = $_GET['search'] ?? '';

// Build query dinamis
$where = [];
$params = [];

if ($status_filter !== '') {
    $where[] = "b.status = ?";
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
if ($min_harga !== '') {
    $where[] = "b.harga_jual >= ?";
    $params[] = $min_harga;
}
if ($max_harga !== '') {
    $where[] = "b.harga_jual <= ?";
    $params[] = $max_harga;
}
if ($ukuran_filter !== '') {
    $where[] = "b.ukuran = ?";
    $params[] = $ukuran_filter;
}
if ($warna_filter !== '') {
    $where[] = "b.warna LIKE ?";
    $params[] = "%$warna_filter%";
}
if ($sumber_filter !== '') {
    $where[] = "b.sumber_barang LIKE ?";
    $params[] = "%$sumber_filter%";
}
if ($search !== '') {
    $where[] = "(b.merek LIKE ? OR b.kode_item LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Hitung total data
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM produk b $whereClause");
$stmtCount->execute($params);
$totalData = $stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Ambil data
$sql = "SELECT b.*, kb.nama_kategori AS kategori FROM produk b LEFT JOIN kategori_produk kb ON b.kategori_id = kb.id $whereClause ORDER BY b.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produkList = $stmt->fetchAll();

// Ambil data untuk dropdown filter
$kategoriList = $pdo->query("SELECT id, nama_kategori FROM kategori_produk ORDER BY nama_kategori ASC")->fetchAll();
$ukuranList = $pdo->query("SELECT DISTINCT ukuran FROM produk WHERE ukuran IS NOT NULL AND ukuran != '' ORDER BY ukuran ASC")->fetchAll();

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-box-seam text-primary me-2"></i>Manajemen Produk</h2>
        <div class="d-flex gap-2">
            <a href="tambah.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Tambah Produk</a>
            <a href="import.php" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i> Import Excel</a>
            <button onclick="cetakSemua()" class="btn btn-dark"><i class="bi bi-upc-scan me-1"></i> Cetak Semua Label</button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-light pb-0 border-0 pt-3">
            <h6 class="fw-bold text-muted"><i class="bi bi-funnel-fill me-1"></i> Filter Pencarian Lanjutan</h6>
        </div>
        <div class="card-body bg-light">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="di_rak" <?= $status_filter == 'di_rak' ? 'selected' : '' ?>>Di Rak</option>
                        <option value="terjual" <?= $status_filter == 'terjual' ? 'selected' : '' ?>>Terjual</option>
                        <option value="rusak" <?= $status_filter == 'rusak' ? 'selected' : '' ?>>Rusak</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Kategori</label>
                    <select name="kategori" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat['id'] ?>" <?= $kategori_filter == $kat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Kondisi</label>
                    <select name="kondisi" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="A" <?= $kondisi_filter == 'A' ? 'selected' : '' ?>>A (Sangat Baik)</option>
                        <option value="B" <?= $kondisi_filter == 'B' ? 'selected' : '' ?>>B (Baik)</option>
                        <option value="C" <?= $kondisi_filter == 'C' ? 'selected' : '' ?>>C (Cukup)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Ukuran</label>
                    <select name="ukuran" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($ukuranList as $uk): ?>
                            <option value="<?= htmlspecialchars($uk['ukuran']) ?>" <?= $ukuran_filter == $uk['ukuran'] ? 'selected' : '' ?>><?= htmlspecialchars($uk['ukuran']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Warna</label>
                    <input type="text" name="warna" class="form-control form-control-sm" placeholder="Contoh: Hitam" value="<?= htmlspecialchars($warna_filter) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Sumber Barang</label>
                    <input type="text" name="sumber" class="form-control form-control-sm" placeholder="Contoh: Bal A" value="<?= htmlspecialchars($sumber_filter) ?>">
                </div>
                
                <!-- Row 2 -->
                <div class="col-md-3">
                    <label class="form-label small text-muted fw-bold">Range Harga (Rp)</label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="min_harga" class="form-control" placeholder="Min" value="<?= htmlspecialchars($min_harga) ?>">
                        <span class="input-group-text">-</span>
                        <input type="number" name="max_harga" class="form-control" placeholder="Max" value="<?= htmlspecialchars($max_harga) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted fw-bold">Pencarian Umum</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Merek atau Kode Item..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-search"></i> Terapkan Filter</button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
                <p class="text-muted mb-0 small">Menampilkan <?= count($produkList) ?> dari <?= $totalData ?> produk</p>
                <div class="small">
                    <strong>Legenda Kondisi:</strong> 
                    <span class="badge bg-success ms-2" data-bs-toggle="tooltip" title="Sangat Baik (tidak ada cacat, seperti baru)">A</span>
                    <span class="badge bg-warning text-dark ms-1" data-bs-toggle="tooltip" title="Baik (ada sedikit cacat minor, layak pakai)">B</span>
                    <span class="badge bg-danger ms-1" data-bs-toggle="tooltip" title="Cukup (ada cacat terlihat, harga murah)">C</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-nowrap mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center">No</th>
                            <th class="text-center">Foto</th>
                            <th>Kode Item</th>
                            <th>Merek</th>
                            <th>Kategori</th>
                            <th>Ukuran</th>
                            <th class="text-center">Kondisi</th>
                            <th class="text-end">Modal</th>
                            <th class="text-end">Harga Jual</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($produkList) > 0): ?>
                            <?php $no = $offset + 1;
                            foreach ($produkList as $b):
                                $hari_di_rak = (strtotime(date('Y-m-d')) - strtotime($b['tanggal_masuk'])) / (60 * 60 * 24);
                                $is_deadstock = ($b['status'] == 'di_rak' && $hari_di_rak > 30);
                            ?>
                                <tr class="<?= $is_deadstock ? 'table-warning' : '' ?>">
                                    <td class="text-center text-muted"><?= $no++ ?></td>
                                    <td class="text-center">
                                        <?php if ($b['foto_produk']): ?>
                                            <img src="<?= BASE_URL . '/' . htmlspecialchars($b['foto_produk']) ?>" alt="Foto" class="rounded object-fit-cover" style="width:50px; height:50px;">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded text-white d-flex align-items-center justify-content-center mx-auto" style="width:50px; height:50px;">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?= htmlspecialchars($b['kode_item']) ?></td>
                                    <td><?= htmlspecialchars($b['merek']) ?></td>
                                    <td><?= htmlspecialchars($b['kategori'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($b['ukuran']) ?></td>
                                    <td class="text-center">
                                        <?php if ($b['kondisi'] == 'A'): ?>
                                            <span class="badge bg-success" data-bs-toggle="tooltip" title="Sangat Baik">A</span>
                                        <?php elseif ($b['kondisi'] == 'B'): ?>
                                            <span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="Baik (Minor Cacat)">B</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" data-bs-toggle="tooltip" title="Cukup (Ada Cacat)">C</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-muted"><?= formatRupiah($b['modal']) ?></td>
                                    <td class="text-end fw-bold text-success"><?= formatRupiah($b['harga_jual']) ?></td>
                                    <td>
                                        <?php if ($b['status'] == 'di_rak'): ?>
                                            <span class="badge bg-primary">Di Rak</span>
                                        <?php elseif ($b['status'] == 'terjual'): ?>
                                            <span class="badge bg-secondary">Terjual</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rusak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="cetak_label.php?id=<?= $b['id'] ?>" target="_blank" class="btn btn-dark" title="Cetak Label"><i class="bi bi-upc-scan"></i></a>
                                            <a href="detail.php?id=<?= $b['id'] ?>" class="btn btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                                            <a href="edit.php?id=<?= $b['id'] ?>" class="btn btn-outline-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <?php if ($b['status'] == 'terjual'): ?>
                                                <button class="btn btn-outline-danger" disabled title="Tidak bisa dihapus (Terjual)"><i class="bi bi-trash"></i></button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $b['id'] ?>)" title="Hapus"><i class="bi bi-trash"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Tidak ada data produk yang sesuai filter.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="p-3 border-top d-flex justify-content-center bg-light">
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query($_GET) ?>">« Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($_GET) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query($_GET) ?>">Next »</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus produk ini? Data yang dihapus tidak dapat dikembalikan.
            </div>
            <div class="modal-footer border-0 pt-0">
                <form method="POST" action="hapus.php" id="deleteForm">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    function confirmDelete(id) {
        document.getElementById('deleteId').value = id;
        var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        myModal.show();
    }

    function cetakSemua() {
        // Menggunakan filter URL saat ini untuk mencetak produk yang sedang difilter
        const params = new URLSearchParams(window.location.search);
        // Hapus param page agar mencetak SEMUA hasil filter, bukan cuma 1 halaman
        params.delete('page');
        window.open('cetak_semua_label.php?' + params.toString(), '_blank');
    }
</script>

<?php include '../includes/footer.php'; ?>