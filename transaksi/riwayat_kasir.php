<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$kasir_id = current_user()['id'];
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Total per hari untuk Ringkasan
$stmtSum = $pdo->prepare("
    SELECT COUNT(*) as jumlah_trx, SUM(harga_jual) as total_pendapatan, SUM(keuntungan) as total_keuntungan 
    FROM v_laporan_transaksi 
    WHERE kasir_id = ? AND tanggal_jual = ?
");
$stmtSum->execute([$kasir_id, $tanggal]);
$sums = $stmtSum->fetch();

$jumlah_trx = $sums['jumlah_trx'] ?? 0;
$total_pendapatan = $sums['total_pendapatan'] ?? 0;
$total_keuntungan = $sums['total_keuntungan'] ?? 0;

$totalPages = ceil($jumlah_trx / $limit);

// Data transaksi list
$stmt = $pdo->prepare("
    SELECT * 
    FROM v_laporan_transaksi 
    WHERE kasir_id = ? AND tanggal_jual = ?
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$kasir_id, $tanggal]);
$transaksiList = $stmt->fetchAll();

$pageTitle = 'Riwayat Transaksi Saya';
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
    body { font-family: Arial, Helvetica, sans-serif; background-color: #f8f9fa; }
    .highlight-new { animation: yellowFade 5s forwards; }
    @keyframes yellowFade {
        from { background-color: #fff3cd; }
        to { background-color: transparent; }
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-clock-history"></i> Riwayat Transaksi Saya</h2>
        <a href="jual.php" class="btn btn-primary"><i class="bi bi-cart-plus"></i> Kembali ke POS</a>
    </div>

    <!-- Ringkasan Hari Ini -->
    <div class="row mb-4">
        <div class="col-md-4 mb-2">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Transaksi Hari Ini</div>
                    <h4 class="mb-0 text-primary">?? <?= number_format($jumlah_trx, 0, ',', '.') ?> <small class="text-muted fs-6">item</small></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Pendapatan Hari Ini</div>
                    <h4 class="mb-0 text-success">?? <?= formatRupiah($total_pendapatan) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Keuntungan Hari Ini</div>
                    <h4 class="mb-0 text-info">? <?= formatRupiah($total_keuntungan) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Pilih Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Lihat</button>
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
                            <th>Waktu</th>
                            <th>Kode Item</th>
                            <th>Merek</th>
                            <th>Ukuran</th>
                            <th>Kondisi</th>
                            <th>Metode Bayar</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-end">Keuntungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transaksiList) > 0): ?>
                            <?php 
                            $no = $offset + 1; 
                            $now = time();
                            foreach ($transaksiList as $t): 
                                $trxTime = strtotime($t['created_at']);
                                $isRecent = ($now - $trxTime) <= 300; // 5 menit
                            ?>
                            <tr class="<?= $isRecent ? 'highlight-new' : '' ?>">
                                <td><?= $no++ ?></td>
                                <td><?= date('H:i', $trxTime) ?></td>
                                <td><?= htmlspecialchars($t['kode_item']) ?></td>
                                <td><?= htmlspecialchars($t['merek']) ?></td>
                                <td><?= htmlspecialchars($t['ukuran']) ?></td>
                                <td>
                                    <?php if($t['kondisi'] == 'A') echo '<span class="badge bg-success">A</span>';
                                    elseif($t['kondisi'] == 'B') echo '<span class="badge bg-warning text-dark">B</span>';
                                    else echo '<span class="badge bg-danger">C</span>'; ?>
                                </td>
                                <td>
                                    <?php if($t['metode_bayar'] == 'tunai') echo '<span class="badge bg-success">Tunai</span>';
                                    elseif($t['metode_bayar'] == 'qris') echo '<span class="badge bg-primary">QRIS</span>';
                                    else echo '<span class="badge bg-info text-dark">Transfer</span>'; ?>
                                </td>
                                <td class="text-end"><?= formatRupiah($t['harga_jual']) ?></td>
                                <td class="text-end fw-bold <?= $t['keuntungan'] > 0 ? 'text-success' : ($t['keuntungan'] < 0 ? 'text-danger' : '') ?>">
                                    <?= formatRupiah($t['keuntungan']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Tidak ada transaksi pada tanggal ini
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="7" class="text-end">TOTAL HARI INI</td>
                            <td class="text-end"><?= formatRupiah($total_pendapatan) ?></td>
                            <td class="text-end text-success"><?= formatRupiah($total_keuntungan) ?></td>
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
                            <a class="page-link" href="?page=<?= $page-1 ?>&tanggal=<?= urlencode($tanggal) ?>">« Prev</a>
                        </li>
                        <?php for($i=1; $i<=$totalPages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&tanggal=<?= urlencode($tanggal) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&tanggal=<?= urlencode($tanggal) ?>">Next »</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.php'; ?>
