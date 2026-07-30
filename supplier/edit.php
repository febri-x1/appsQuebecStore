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

$supplier = safeExecute($pdo, "SELECT * FROM suppliers WHERE id = ?", [$id])->fetch();
if (!$supplier) { flash('error', 'Supplier tidak ditemukan.'); redirect('supplier/index.php'); }

$pageTitle = 'Edit Supplier';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_supplier = trim($_POST['nama_supplier'] ?? '');
    $telepon       = trim($_POST['telepon'] ?? '');
    $alamat        = trim($_POST['alamat'] ?? '');
    $harga_per_bal = (float) str_replace(['.', ','], ['', '.'], $_POST['harga_per_bal'] ?? '0');
    $isi_per_bal   = (int) ($_POST['isi_per_bal'] ?? 0);
    $keterangan    = trim($_POST['keterangan'] ?? '');

    if (empty($nama_supplier)) $errors[] = 'Nama supplier wajib diisi.';
    if ($harga_per_bal <= 0)   $errors[] = 'Harga per bal harus lebih dari 0.';
    if ($isi_per_bal <= 0)     $errors[] = 'Isi per bal harus lebih dari 0.';

    // Cek duplikat nama (selain diri sendiri)
    if (empty($errors)) {
        $cek = safeExecute($pdo, "SELECT id FROM suppliers WHERE nama_supplier = ? AND id != ? LIMIT 1", [$nama_supplier, $id])->fetch();
        if ($cek) $errors[] = 'Nama supplier "' . htmlspecialchars($nama_supplier) . '" sudah terdaftar.';
    }

    if (empty($errors)) {
        try {
            safeExecute($pdo, "
                UPDATE suppliers SET nama_supplier=?, telepon=?, alamat=?, harga_per_bal=?, isi_per_bal=?, keterangan=?
                WHERE id=?
            ", [$nama_supplier, $telepon ?: null, $alamat ?: null, $harga_per_bal, $isi_per_bal, $keterangan ?: null, $id]);

            flash('success', 'Data supplier <strong>' . htmlspecialchars($nama_supplier) . '</strong> berhasil diperbarui.');
            redirect('supplier/index.php');
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
    // Simpan input lama untuk refill form
    $supplier = array_merge($supplier, $_POST, ['harga_per_bal' => $harga_per_bal, 'isi_per_bal' => $isi_per_bal]);
}

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif; max-width: 820px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Supplier</h2>
            <p class="text-muted small mb-0">Perbarui informasi supplier: <strong><?= htmlspecialchars($supplier['nama_supplier']) ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="detail.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-eye me-1"></i>Detail</a>
            <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Terdapat kesalahan:</strong>
        <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-truck text-warning me-2"></i>Informasi Supplier</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="" novalidate>

                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="nama_supplier">Nama Supplier <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-truck text-primary"></i></span>
                            <input type="text" name="nama_supplier" id="nama_supplier"
                                   class="form-control form-control-lg"
                                   value="<?= htmlspecialchars($supplier['nama_supplier']) ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="telepon"><i class="bi bi-telephone text-muted me-1"></i>Nomor Telepon</label>
                        <input type="tel" name="telepon" id="telepon" class="form-control"
                               value="<?= htmlspecialchars($supplier['telepon'] ?? '') ?>" placeholder="081234567890">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="alamat"><i class="bi bi-geo-alt text-muted me-1"></i>Alamat</label>
                        <input type="text" name="alamat" id="alamat" class="form-control"
                               value="<?= htmlspecialchars($supplier['alamat'] ?? '') ?>" placeholder="Kota, Provinsi">
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="fw-bold text-muted mb-3"><i class="bi bi-calculator me-2"></i>Kalkulasi Modal</h6>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="harga_per_bal">Harga per Bal (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white fw-bold">Rp</span>
                            <input type="number" name="harga_per_bal" id="harga_per_bal"
                                   class="form-control form-control-lg" min="1" step="1000"
                                   value="<?= htmlspecialchars($supplier['harga_per_bal']) ?>"
                                   oninput="hitungModal()" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="isi_per_bal">Estimasi Isi per Bal (Pcs) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="isi_per_bal" id="isi_per_bal"
                                   class="form-control form-control-lg" min="1" step="1"
                                   value="<?= htmlspecialchars($supplier['isi_per_bal']) ?>"
                                   oninput="hitungModal()" required>
                            <span class="input-group-text">pcs</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="alert border-0 d-flex align-items-center gap-3 py-3"
                             style="background:#f0fdf4; border-left:4px solid #22c55e !important;">
                            <div class="fs-3 text-success"><i class="bi bi-calculator-fill"></i></div>
                            <div>
                                <div class="text-muted small fw-semibold">Estimasi Modal per Item</div>
                                <div class="fw-bold fs-4 text-success" id="modalPerItem">—</div>
                                <small class="text-muted">Nilai saat ini di database: <strong><?= formatRupiah($supplier['modal_per_item'] ?? 0) ?></strong></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label fw-semibold" for="keterangan"><i class="bi bi-chat-text text-muted me-1"></i>Keterangan</label>
                    <textarea name="keterangan" id="keterangan" class="form-control" rows="3"
                              placeholder="Catatan tambahan..."><?= htmlspecialchars($supplier['keterangan'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-3 mt-4 pt-2 border-top">
                    <button type="submit" class="btn btn-warning btn-lg px-5 fw-bold">
                        <i class="bi bi-save2 me-2"></i>Simpan Perubahan
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg px-4">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function hitungModal() {
    const harga = parseFloat(document.getElementById('harga_per_bal').value) || 0;
    const isi   = parseInt(document.getElementById('isi_per_bal').value) || 0;
    const el    = document.getElementById('modalPerItem');
    if (harga > 0 && isi > 0) {
        el.textContent = 'Rp ' + (harga / isi).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2});
    } else {
        el.textContent = '—';
    }
}
document.addEventListener('DOMContentLoaded', hitungModal);
</script>
<?php include '../includes/footer.php'; ?>
