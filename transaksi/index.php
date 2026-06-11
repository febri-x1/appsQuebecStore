<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
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
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM v_laporan_transaksi $whereClause");
$stmtCount->execute($params);
$totalData = $stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Sum query for KPI and Footer
$stmtSum = $pdo->prepare("SELECT SUM(harga_jual) as total_pendapatan, SUM(modal) as total_modal, SUM(keuntungan) as total_keuntungan FROM v_laporan_transaksi $whereClause");
$stmtSum->execute($params);
$sums = $stmtSum->fetch();

$total_pendapatan = $sums['total_pendapatan'] ?? 0;
$total_modal = $sums['total_modal'] ?? 0;
$total_keuntungan = $sums['total_keuntungan'] ?? 0;

// Fetch data
$sql = "SELECT * FROM v_laporan_transaksi $whereClause ORDER BY tanggal_jual DESC, transaksi_id DESC LIMIT $limit OFFSET $offset";
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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Riwayat Transaksi</h2>
        <div>
            <a href="../laporan/export_pdf.php?dari=<?= urlencode($dari) ?>&sampai=<?= urlencode($sampai) ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> Ekspor PDF</a>
            <a href="../laporan/export_excel.php?dari=<?= urlencode($dari) ?>&sampai=<?= urlencode($sampai) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Ekspor Excel</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-2">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total Transaksi</div>
                    <h4 class="mb-0 text-primary">🧾 <?= number_format($totalData, 0, ',', '.') ?> <small class="text-muted fs-6">trx</small></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total Pendapatan</div>
                    <h4 class="mb-0 text-success">💰 <?= formatRupiah($total_pendapatan) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total Modal</div>
                    <h4 class="mb-0 text-warning">📉 <?= formatRupiah($total_modal) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total Keuntungan</div>
                    <h4 class="mb-0 text-info">✨ <?= formatRupiah($total_keuntungan) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Tanggal Dari</label>
                    <input type="date" name="dari" class="form-control" value="<?= htmlspecialchars($dari) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tanggal Sampai</label>
                    <input type="date" name="sampai" class="form-control" value="<?= htmlspecialchars($sampai) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kasir</label>
                    <select name="kasir_id" class="form-select">
                        <option value="">Semua Kasir</option>
                        <?php foreach($kasirList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $kasir_id == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Metode Bayar</label>
                    <select name="metode_bayar" class="form-select">
                        <option value="">Semua</option>
                        <option value="tunai" <?= $metode_bayar == 'tunai' ? 'selected' : '' ?>>Tunai</option>
                        <option value="qris" <?= $metode_bayar == 'qris' ? 'selected' : '' ?>>QRIS</option>
                        <option value="transfer" <?= $metode_bayar == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cari (Kode/Merek)</label>
                    <input type="text" name="search" class="form-control" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Filter</button>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Kode Item</th>
                            <th>Merek</th>
                            <th>Ukuran</th>
                            <th>Kondisi</th>
                            <th>Kasir</th>
                            <th>Metode Bayar</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-end">Modal</th>
                            <th class="text-end">Keuntungan</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transaksiList) > 0): ?>
                            <?php $no = $offset + 1; foreach ($transaksiList as $t): 
                                $isToday = (date('Y-m-d', strtotime($t['created_at'])) === date('Y-m-d'));
                            ?>
                            <tr style="<?= $isToday ? 'border-left: 4px solid #0d6efd;' : '' ?>">
                                <td><?= $no++ ?></td>
                                <td><?= formatTanggal($t['tanggal_jual']) ?></td>
                                <td><?= htmlspecialchars($t['kode_item']) ?></td>
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
                                <td class="text-end"><?= formatRupiah($t['harga_jual']) ?></td>
                                <td class="text-end"><?= formatRupiah($t['modal']) ?></td>
                                <td class="text-end fw-bold <?= $t['keuntungan'] > 0 ? 'text-success' : ($t['keuntungan'] < 0 ? 'text-danger' : '') ?>">
                                    <?= formatRupiah($t['keuntungan']) ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="detail.php?id=<?= $t['transaksi_id'] ?>" class="btn btn-info text-white">Detail</a>
                                        <?php if($isToday): ?>
                                            <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $t['transaksi_id'] ?>, '<?= htmlspecialchars($t['kode_item'].' - '.$t['merek']) ?>', '<?= formatTanggal($t['tanggal_jual']) ?>')"><i class="bi bi-trash"></i> Hapus</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Belum ada transaksi pada periode ini
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="8" class="text-end">TOTAL KESELURUHAN (Sesuai Filter)</td>
                            <td class="text-end"><?= formatRupiah($total_pendapatan) ?></td>
                            <td class="text-end"><?= formatRupiah($total_modal) ?></td>
                            <td class="text-end text-success"><?= formatRupiah($total_keuntungan) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="p-3 border-top d-flex justify-content-center">
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
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Konfirmasi Hapus Transaksi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Anda yakin ingin menghapus transaksi ini?</p>
        <ul class="list-group mb-3">
            <li class="list-group-item"><strong>Barang:</strong> <span id="delBarang"></span></li>
            <li class="list-group-item"><strong>Tanggal:</strong> <span id="delTanggal"></span></li>
        </ul>
        <div class="alert alert-warning mb-0">
            <strong>Peringatan!</strong> Menghapus transaksi akan mengembalikan status barang menjadi <strong>Di Rak</strong>. Aksi ini tidak dapat dibatalkan.
        </div>
      </div>
      <div class="modal-footer">
        <form method="POST" action="hapus.php">
            <?php
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            ?>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="deleteId">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Ya, Hapus Transaksi</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, barang, tanggal) {
    document.getElementById('deleteId').value = id;
    document.getElementById('delBarang').innerText = barang;
    document.getElementById('delTanggal').innerText = tanggal;
    var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    myModal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
