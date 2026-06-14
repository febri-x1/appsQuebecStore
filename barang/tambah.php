<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

// Generate CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil supplier
$suppliers = $pdo->query("SELECT id, nama_supplier, modal_per_item FROM suppliers ORDER BY nama_supplier ASC")->fetchAll();
$kategori_list = $pdo->query("SELECT id, nama_kategori FROM kategori_barang ORDER BY nama_kategori ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $kode_item = sanitizeInput($_POST['kode_item']);
    $supplier_id = (int)$_POST['supplier_id'];
    $merek = sanitizeInput($_POST['merek']);
    $kategori_id = (int)$_POST['kategori_id'];
    $ukuran = sanitizeInput($_POST['ukuran'] === 'Lainnya' ? $_POST['ukuran_manual'] : $_POST['ukuran']);
    $warna = sanitizeInput($_POST['warna']);
    $kondisi = sanitizeInput($_POST['kondisi']);
    $deskripsi = sanitizeInput($_POST['deskripsi']);
    $modal = (float)$_POST['modal'];
    $harga_jual = (float)$_POST['harga_jual'];
    $tanggal_masuk = $_POST['tanggal_masuk'] ?: date('Y-m-d');

    $fotoPath = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['foto']['size'] <= 2000000) {
            $fileName = uniqid() . '.' . $ext;
            $targetDir = '../assets/img/uploads/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetDir . $fileName)) {
                $fotoPath = 'assets/img/uploads/' . $fileName;
            }
        } else {
            flash('error', 'Foto tidak valid (Maks 2MB, hanya JPG/PNG/WEBP).');
            redirect('barang/tambah.php');
        }
    }

    $stmt = $pdo->prepare('INSERT INTO barang (kode_item, supplier_id, merek, kategori_id, ukuran, warna, kondisi, deskripsi, foto, modal, harga_jual, status, tanggal_masuk) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "di_rak", ?)');
    $stmt->execute([
        $kode_item,
        $supplier_id,
        $merek,
        $kategori_id,
        $ukuran,
        $warna,
        $kondisi,
        $deskripsi,
        $fotoPath,
        $modal,
        $harga_jual,
        $tanggal_masuk
    ]);

    flash('success', 'Barang berhasil ditambahkan.');
    redirect('barang/index.php');
}

$pageTitle = 'Tambah Barang';
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f8f9fa;
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h4 class="mb-0">Tambah Barang Baru (Restock)</h4>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="formTambah">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Kode Item</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="kode_item" id="kode_item" value="<?= generateKodeItem($pdo) ?>" readonly required>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">Generate Ulang</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="form-select" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" data-modal="<?= $sup['modal_per_item'] ?>">
                                    <?= htmlspecialchars($sup['nama_supplier']) ?> (Modal: <?= formatRupiah($sup['modal_per_item']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Merek</label>
                        <input type="text" name="merek" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($kategori_list as $kat): ?>
                                <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Warna</label>
                        <input type="text" name="warna" class="form-control" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Ukuran</label>
                        <select name="ukuran" id="ukuran" class="form-select" required onchange="toggleUkuran()">
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M" selected>M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="Lainnya">Lainnya...</option>
                        </select>
                        <input type="text" name="ukuran_manual" id="ukuran_manual" class="form-control mt-2" placeholder="Isi manual" style="display:none;">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label d-block">Kondisi</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="kondisi" value="A" required>
                            <label class="form-check-label">A (Sangat Baik)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="kondisi" value="B">
                            <label class="form-check-label">B (Baik)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="kondisi" value="C">
                            <label class="form-check-label">C (Cukup)</label>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Modal per Item (Rp)</label>
                        <input type="number" name="modal" id="modal" class="form-control" step="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" id="harga_jual" class="form-control" step="0.01" required>
                        <small class="text-success fw-bold mt-1 d-block" id="kalkulasiUntung">Keuntungan: Rp 0</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Deskripsi (Opsional)</label>
                        <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Foto Barang (Opsional, Max 2MB)</label>
                        <input type="file" name="foto" id="foto" class="form-control" accept="image/*">
                        <img id="previewFoto" src="#" alt="Preview" class="img-thumbnail mt-2" style="display:none; max-height:150px;">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Masuk</label>
                        <input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan Barang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto modal from supplier
    document.getElementById('supplier_id').addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        var modal = selected.getAttribute('data-modal');
        if (modal) {
            document.getElementById('modal').value = parseFloat(modal);
            hitungUntung();
        }
    });

    // Kalkulasi untung
    function hitungUntung() {
        var modal = parseFloat(document.getElementById('modal').value) || 0;
        var jual = parseFloat(document.getElementById('harga_jual').value) || 0;
        var untung = jual - modal;
        var format = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(untung);
        document.getElementById('kalkulasiUntung').innerText = "Keuntungan: " + format;
    }
    document.getElementById('modal').addEventListener('input', hitungUntung);
    document.getElementById('harga_jual').addEventListener('input', hitungUntung);

    // Toggle Ukuran
    function toggleUkuran() {
        var sel = document.getElementById('ukuran');
        var man = document.getElementById('ukuran_manual');
        if (sel.value === 'Lainnya') {
            man.style.display = 'block';
            man.required = true;
        } else {
            man.style.display = 'none';
            man.required = false;
        }
    }

    // Preview Foto
    document.getElementById('foto').addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewFoto').style.display = 'block';
                document.getElementById('previewFoto').src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Loading state
    document.getElementById('formTambah').addEventListener('submit', function() {
        var btn = document.getElementById('btnSubmit');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...';
        btn.disabled = true;
    });
</script>

<?php include '../includes/footer.php'; ?>