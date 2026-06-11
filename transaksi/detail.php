<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM v_laporan_transaksi WHERE transaksi_id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t) {
    flash('error', 'Transaksi tidak ditemukan.');
    redirect('transaksi/index.php');
}

$isToday = (date('Y-m-d', strtotime($t['created_at'])) === date('Y-m-d'));
$margin = $t['harga_jual'] > 0 ? ($t['keuntungan'] / $t['harga_jual']) * 100 : 0;

$pageTitle = 'Detail Transaksi #' . str_pad($t['transaksi_id'], 5, '0', STR_PAD_LEFT);
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Detail Transaksi</h2>
        <div>
            <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Transaksi</a>
        </div>
    </div>

    <div class="row">
        <!-- Kolom Kiri - Info Transaksi -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm h-100 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> Info Transaksi</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">ID Transaksi</th>
                            <td>: #<?= str_pad($t['transaksi_id'], 5, '0', STR_PAD_LEFT) ?></td>
                        </tr>
                        <tr>
                            <th>Tanggal Jual</th>
                            <td>: <?= formatTanggal($t['tanggal_jual']) ?></td>
                        </tr>
                        <tr>
                            <th>Kasir</th>
                            <td>: <?= htmlspecialchars($t['nama_kasir']) ?></td>
                        </tr>
                        <tr>
                            <th>Metode Bayar</th>
                            <td>: 
                                <?php if($t['metode_bayar'] == 'tunai'): ?>
                                    <span class="badge bg-success">Tunai</span>
                                <?php elseif($t['metode_bayar'] == 'qris'): ?>
                                    <span class="badge bg-primary">QRIS</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">Transfer</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Catatan</th>
                            <td>: <?= nl2br(htmlspecialchars($t['catatan'] ?: '-')) ?></td>
                        </tr>
                        <tr>
                            <th>Dibuat pada</th>
                            <td>: <?= date('d M Y H:i:s', strtotime($t['created_at'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan - Info Barang -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm h-100 border-info">
                <div class="card-header bg-info text-dark">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Info Barang</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Kode Item</th>
                            <td>: <?= htmlspecialchars($t['kode_item']) ?></td>
                        </tr>
                        <tr>
                            <th>Merek</th>
                            <td>: <?= htmlspecialchars($t['merek']) ?></td>
                        </tr>
                        <tr>
                            <th>Kategori</th>
                            <td>: <?= ucfirst(str_replace('_', ' ', $t['kategori'])) ?></td>
                        </tr>
                        <tr>
                            <th>Ukuran</th>
                            <td>: <?= htmlspecialchars($t['ukuran']) ?></td>
                        </tr>
                        <tr>
                            <th>Kondisi</th>
                            <td>: 
                                <?php if($t['kondisi'] == 'A'): ?>
                                    <span class="badge bg-success">A</span>
                                <?php elseif($t['kondisi'] == 'B'): ?>
                                    <span class="badge bg-warning text-dark">B</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">C</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Supplier</th>
                            <td>: <?= htmlspecialchars($t['nama_supplier']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Kalkulasi Keuangan -->
    <div class="row mt-2">
        <div class="col-md-6 offset-md-3">
            <div class="card shadow-sm border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Harga Jual</span>
                        <strong><?= formatRupiah($t['harga_jual']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Modal Item</span>
                        <strong><?= formatRupiah($t['modal']) ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2 fs-5">
                        <span class="text-success fw-bold">Keuntungan</span>
                        <strong class="text-success"><?= formatRupiah($t['keuntungan']) ?> <?= $t['keuntungan'] > 0 ? '?' : '?' ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Margin</span>
                        <strong><?= number_format($margin, 1, ',', '.') ?>%</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Aksi Hapus -->
    <?php if ($isToday): ?>
    <div class="text-center mt-5">
        <button class="btn btn-outline-danger" onclick="confirmDelete()"><i class="bi bi-trash"></i> Hapus Transaksi</button>
        <p class="text-muted small mt-2">Tombol hapus hanya tersedia untuk transaksi yang dibuat hari ini.</p>
    </div>

    <!-- Modal Hapus -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Konfirmasi Hapus Transaksi</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-start">
            <p>Anda yakin ingin menghapus transaksi ini?</p>
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
                <input type="hidden" name="id" value="<?= $t['transaksi_id'] ?>">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Ya, Hapus Transaksi</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmDelete() {
        var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        myModal.show();
    }
    </script>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>
