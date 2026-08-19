<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'kasir') {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk pemilik.');
    redirect('dashboard.php');
}

$id = (int)($_GET['id'] ?? 0);

// Generate CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $tanggal_jual = $_POST['tanggal_jual'];
    $kasir_id = (int)$_POST['kasir_id'];
    $metode_bayar = $_POST['metode_bayar'];
    $harga_jual = (float)$_POST['harga_jual'];
    $catatan = sanitizeInput($_POST['catatan']);

    $stmt = $pdo->prepare("UPDATE transaksi SET tanggal_jual = ?, kasir_id = ?, metode_bayar = ?, harga_jual = ?, catatan = ? WHERE id = ?");
    if ($stmt->execute([$tanggal_jual, $kasir_id, $metode_bayar, $harga_jual, $catatan, $id])) {
        flash('success', 'Transaksi berhasil diperbarui.');
        redirect('transaksi/index.php');
    } else {
        flash('error', 'Gagal memperbarui transaksi.');
    }
}

$stmt = $pdo->prepare("
    SELECT t.*, p.kode_item, p.merek, p.ukuran, p.modal as modal_awal
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    flash('error', 'Transaksi tidak ditemukan.');
    redirect('transaksi/index.php');
}

$kasirList = $pdo->query("SELECT id, nama FROM users WHERE role = 'kasir' ORDER BY nama ASC")->fetchAll();

$pageTitle = 'Edit Transaksi';
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5">
    <div class="card shadow-sm border-0 mx-auto" style="max-width: 800px;">
        <div class="card-header bg-white pt-4 pb-3 border-0">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Transaksi</h4>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
            </div>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <div class="alert alert-info mb-4 border-0 shadow-sm">
                <div class="row">
                    <div class="col-sm-4">
                        <small class="text-muted d-block text-uppercase fw-bold" style="letter-spacing:1px; font-size:0.7rem;">Produk</small>
                        <strong><?= htmlspecialchars($trx['kode_item']) ?> - <?= htmlspecialchars($trx['merek']) ?> (<?= htmlspecialchars($trx['ukuran']) ?>)</strong>
                    </div>
                    <div class="col-sm-4 text-sm-center mt-2 mt-sm-0">
                        <small class="text-muted d-block text-uppercase fw-bold" style="letter-spacing:1px; font-size:0.7rem;">Jumlah (Fix)</small>
                        <strong><?= htmlspecialchars($trx['qty']) ?> Pcs</strong>
                    </div>
                    <div class="col-sm-4 text-sm-end mt-2 mt-sm-0">
                        <small class="text-muted d-block text-uppercase fw-bold" style="letter-spacing:1px; font-size:0.7rem;">Modal Satuan (Fix)</small>
                        <strong><?= formatRupiah($trx['modal']) ?></strong>
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">Tanggal Jual</label>
                        <input type="date" name="tanggal_jual" class="form-control" value="<?= htmlspecialchars($trx['tanggal_jual']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">Kasir</label>
                        <select name="kasir_id" class="form-select" required>
                            <option value="">Pilih Kasir</option>
                            <?php foreach($kasirList as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= $trx['kasir_id'] == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">Metode Pembayaran</label>
                        <select name="metode_bayar" class="form-select" required>
                            <option value="tunai" <?= $trx['metode_bayar'] == 'tunai' ? 'selected' : '' ?>>Tunai</option>
                            <option value="qris" <?= $trx['metode_bayar'] == 'qris' ? 'selected' : '' ?>>QRIS</option>
                            <option value="transfer" <?= $trx['metode_bayar'] == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">Harga Jual Satuan (Rp)</label>
                        <input type="number" name="harga_jual" class="form-control text-success fw-bold" step="0.01" value="<?= htmlspecialchars($trx['harga_jual']) ?>" required>
                        <div class="form-text text-warning"><i class="bi bi-info-circle"></i> Mengubah harga jual akan otomatis menghitung ulang keuntungan.</div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small">Catatan (Opsional)</label>
                    <textarea name="catatan" class="form-control" rows="3"><?= htmlspecialchars($trx['catatan']) ?></textarea>
                </div>

                <hr class="border-secondary opacity-25">
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-light px-4">Batal</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
