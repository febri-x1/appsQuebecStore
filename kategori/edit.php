<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Pastikan kasir tidak bisa akses
if (current_user()['role'] === 'kasir') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard_kasir.php');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'ID Kategori tidak valid.');
    redirect('kategori/index.php');
}

// Generate CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil data kategori
$stmt = $pdo->prepare("SELECT * FROM kategori_barang WHERE id = ?");
$stmt->execute([$id]);
$kategori = $stmt->fetch();

if (!$kategori) {
    flash('error', 'Kategori tidak ditemukan.');
    redirect('kategori/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $nama_kategori = sanitizeInput($_POST['nama_kategori']);
    $deskripsi = sanitizeInput($_POST['deskripsi']);

    // Cek apakah nama kategori sudah ada dan bukan milik id ini
    $stmtCek = $pdo->prepare("SELECT id FROM kategori_barang WHERE nama_kategori = ? AND id != ?");
    $stmtCek->execute([$nama_kategori, $id]);
    if ($stmtCek->fetch()) {
        flash('error', 'Nama kategori sudah digunakan.');
        redirect('kategori/edit.php?id=' . $id);
    }

    $stmtUpdate = $pdo->prepare('UPDATE kategori_barang SET nama_kategori = ?, deskripsi = ? WHERE id = ?');
    $stmtUpdate->execute([$nama_kategori, $deskripsi, $id]);

    flash('success', 'Kategori berhasil diperbarui.');
    redirect('kategori/index.php');
}

$pageTitle = 'Edit Kategori';
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h4 class="mb-0">Edit Kategori</h4>
        </div>
        <div class="card-body">
            <form method="POST" id="formEdit">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-3">
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="nama_kategori" class="form-control" required value="<?= htmlspecialchars($kategori['nama_kategori']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi (Opsional)</label>
                    <textarea name="deskripsi" class="form-control" rows="4"><?= htmlspecialchars($kategori['deskripsi'] ?? '') ?></textarea>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('formEdit').addEventListener('submit', function() {
        var btn = document.getElementById('btnSubmit');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...';
        btn.disabled = true;
    });
</script>

<?php include '../includes/footer.php'; ?>