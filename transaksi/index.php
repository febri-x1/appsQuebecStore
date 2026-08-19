<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'kasir') {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk pemilik.');
    redirect('dashboard.php');
}

$pageTitle = 'Manajemen Transaksi';

// Filters
$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$kasir_id = $_GET['kasir_id'] ?? '';
$metode_bayar = $_GET['metode_bayar'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

// Where clause
$where = ["tanggal_jual BETWEEN ? AND ?"];
$params = [$dari, $sampai];

if ($kasir_id !== '') {
    $where[] = "kasir_id = ?";
    $params[] = $kasir_id;
}
if ($metode_bayar !== '') {
    $where[] = "metode_bayar = ?";
    $params[] = $metode_bayar;
}
if ($search !== '') {
    $where[] = "(kode_item LIKE ? OR merek LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Base count query
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.qty, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi $whereClause");
$stmtCount->execute($params);
$totalData = $stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Sum query for Footer
$stmtSum = $pdo->prepare("SELECT SUM(harga_jual * qty) as total_pendapatan, SUM(modal * qty) as total_modal FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.qty, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi $whereClause");
$stmtSum->execute($params);
$sums = $stmtSum->fetch();

$total_pendapatan = $sums['total_pendapatan'] ?? 0;
$total_modal = $sums['total_modal'] ?? 0;

// Fetch data
$sql = "SELECT * FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.qty, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi $whereClause ORDER BY tanggal_jual DESC, transaksi_id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transaksiList = $stmt->fetchAll();

// Get Kasir list
$kasirList = $pdo->query("SELECT id, nama FROM users WHERE role = 'kasir' ORDER BY nama ASC")->fetchAll();

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-receipt"></i> Manajemen Transaksi</h2>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body bg-light rounded">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Tanggal Dari</label>
                    <input type="date" name="dari" class="form-control" value="<?= htmlspecialchars($dari) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Tanggal Sampai</label>
                    <input type="date" name="sampai" class="form-control" value="<?= htmlspecialchars($sampai) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Kasir</label>
                    <select name="kasir_id" class="form-select">
                        <option value="">Semua Kasir</option>
                        <?php foreach($kasirList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $kasir_id == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Metode Bayar</label>
                    <select name="metode_bayar" class="form-select">
                        <option value="">Semua</option>
                        <option value="tunai" <?= $metode_bayar == 'tunai' ? 'selected' : '' ?>>Tunai</option>
                        <option value="qris" <?= $metode_bayar == 'qris' ? 'selected' : '' ?>>QRIS</option>
                        <option value="transfer" <?= $metode_bayar == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Cari (Kode/Merek)</label>
                    <input type="text" name="search" class="form-control" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filter</button>
                    <a href="index.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-nowrap mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center">No</th>
                            <th>Tanggal</th>
                            <th>Kode Item</th>
                            <th>Merek</th>
                            <th>Ukuran</th>
                            <th>Kondisi</th>
                            <th>Kasir</th>
                            <th>Metode Bayar</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Harga Jual (Total)</th>
                            <th class="text-end">Modal (Total)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transaksiList) > 0): ?>
                            <?php $no = $offset + 1; foreach ($transaksiList as $t): ?>
                            <tr>
                                <td class="text-center text-muted"><?= $no++ ?></td>
                                <td><?= formatTanggal($t['tanggal_jual']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($t['kode_item']) ?></td>
                                <td><?= htmlspecialchars($t['merek']) ?></td>
                                <td><?= htmlspecialchars($t['ukuran']) ?></td>
                                <td>
                                    <?php if($t['kondisi'] == 'A'): ?>
                                        <span class="badge bg-success">A</span>
                                    <?php elseif($t['kondisi'] == 'B'): ?>
                                        <span class="badge bg-warning text-dark">B</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">C</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($t['nama_kasir']) ?></td>
                                <td>
                                    <?php if($t['metode_bayar'] == 'tunai'): ?>
                                        <span class="badge bg-success">Tunai</span>
                                    <?php elseif($t['metode_bayar'] == 'qris'): ?>
                                        <span class="badge bg-primary">QRIS</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Transfer</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold text-primary"><?= htmlspecialchars($t['qty']) ?></td>
                                <td class="text-end fw-bold text-success"><?= formatRupiah($t['harga_jual'] * $t['qty']) ?></td>
                                <td class="text-end text-muted"><?= formatRupiah($t['modal'] * $t['qty']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="detail.php?id=<?= $t['transaksi_id'] ?>" class="btn btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                                        <a href="edit.php?id=<?= $t['transaksi_id'] ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $t['transaksi_id'] ?>, '<?= htmlspecialchars($t['kode_item'].' - '.$t['merek']) ?>', '<?= formatTanggal($t['tanggal_jual']) ?>')" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Belum ada transaksi pada periode ini
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="9" class="text-end text-uppercase">TOTAL KESELURUHAN (Sesuai Filter)</td>
                            <td class="text-end text-success"><?= formatRupiah($total_pendapatan) ?></td>
                            <td class="text-end text-muted"><?= formatRupiah($total_modal) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="p-3 border-top d-flex justify-content-center bg-light">
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>&dari=<?= urlencode($dari) ?>&sampai=<?= urlencode($sampai) ?>&kasir_id=<?= urlencode($kasir_id) ?>&metode_bayar=<?= urlencode($metode_bayar) ?>&search=<?= urlencode($search) ?>">« Prev</a>
                        </li>
                        <?php for($i=1; $i<=$totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&dari=<?= urlencode($dari) ?>&sampai=<?= urlencode($sampai) ?>&kasir_id=<?= urlencode($kasir_id) ?>&metode_bayar=<?= urlencode($metode_bayar) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&dari=<?= urlencode($dari) ?>&sampai=<?= urlencode($sampai) ?>&kasir_id=<?= urlencode($kasir_id) ?>&metode_bayar=<?= urlencode($metode_bayar) ?>&search=<?= urlencode($search) ?>">Next »</a>
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
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Anda yakin ingin menghapus transaksi ini?</p>
        <div class="bg-light p-3 rounded mb-3 border">
            <div class="mb-2"><strong>Produk:</strong> <span id="delProduk" class="text-primary"></span></div>
            <div><strong>Tanggal:</strong> <span id="delTanggal"></span></div>
        </div>
        <div class="alert alert-danger mb-0 py-2 border-0">
            <i class="bi bi-info-circle-fill me-2"></i>Stok produk akan dikembalikan.
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <form method="POST" action="hapus.php">
            <?php
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            ?>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
function confirmDelete(id, produk, tanggal) {
    document.getElementById('deleteId').value = id;
    document.getElementById('delProduk').innerText = produk;
    document.getElementById('delTanggal').innerText = tanggal;
    var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    myModal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
