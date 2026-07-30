<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Pastikan kasir tidak bisa akses
if (current_user()['role'] === 'kasir') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard_kasir.php');
}

// Generate CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $nama_kategori = sanitizeInput($_POST['nama_kategori']);
    $deskripsi = sanitizeInput($_POST['deskripsi']);

    // Cek apakah nama kategori sudah ada
    $stmtCek = $pdo->prepare("SELECT id FROM kategori_produk WHERE nama_kategori = ?");
    $stmtCek->execute([$nama_kategori]);
    if ($stmtCek->fetch()) {
        flash('error', 'Nama kategori sudah digunakan.');
        redirect('kategori/tambah.php');
    }

    $stmt = $pdo->prepare('INSERT INTO kategori_produk (nama_kategori, deskripsi) VALUES (?, ?)');
    $stmt->execute([$nama_kategori, $deskripsi]);

    flash('success', 'Kategori berhasil ditambahkan.');
    redirect('kategori/index.php');
}

$pageTitle = 'Tambah Kategori';
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h4 class="mb-0">Tambah Kategori</h4>
        </div>
        <div class="card-body">
            <form method="POST" id="formTambah">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-3">
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="nama_kategori" class="form-control" required placeholder="Contoh: Kaos, Kemeja, Sepatu">
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi (Opsional)</label>
                    <textarea name="deskripsi" class="form-control" rows="4"></textarea>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('formTambah').addEventListener('submit', function() {
        var btn = document.getElementById('btnSubmit');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...';
        btn.disabled = true;
    });
</script>

<?php include '../includes/footer.php'; ?>