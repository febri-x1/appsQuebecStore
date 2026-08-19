<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'admin') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

// Generate CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil supplier & kategori
$suppliers = $pdo->query("SELECT id, nama_supplier, modal_per_item FROM suppliers ORDER BY nama_supplier ASC")->fetchAll();
$kategori_list = $pdo->query("SELECT id, nama_kategori FROM kategori_produk ORDER BY nama_kategori ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $kode_item = sanitizeInput($_POST['kode_item']);
    $supplier_id = (int)$_POST['supplier_id'];
    $merek = sanitizeInput($_POST['merek'] ?? '');
    if (empty($merek)) $merek = '-';
    $kategori_id = (int)$_POST['kategori_id'];
    $ukuran = sanitizeInput($_POST['ukuran'] === 'Lainnya' ? $_POST['ukuran_manual'] : $_POST['ukuran']);
    $warna = sanitizeInput($_POST['warna']);
    $bahan = sanitizeInput($_POST['bahan']);
    $kondisi = sanitizeInput($_POST['kondisi']);
    $deskripsi = sanitizeInput($_POST['deskripsi']);
    $sumber_barang = sanitizeInput($_POST['sumber_barang']);
    $keterangan_sumber = sanitizeInput($_POST['keterangan_sumber']);
    $modal = (float)$_POST['modal'];
    $harga_jual = (float)$_POST['harga_jual'];
    $tanggal_masuk = $_POST['tanggal_masuk'] ?: date('Y-m-d');

    $fotoPath = null;
    if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['foto_produk']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['foto_produk']['size'] <= 2000000) {
            $fileName = uniqid() . '.' . $ext;
            $targetDir = '../assets/img/produk/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            if (move_uploaded_file($_FILES['foto_produk']['tmp_name'], $targetDir . $fileName)) {
                $fotoPath = 'assets/img/produk/' . $fileName;
            }
        } else {
            flash('error', 'Foto tidak valid (Maks 2MB, hanya JPG/JPEG/PNG).');
            redirect('produk/tambah.php');
        }
    }

    $qty = (int)$_POST['qty'];

    $stmt = $pdo->prepare('INSERT INTO produk (kode_item, supplier_id, merek, kategori_id, ukuran, warna, bahan, kondisi, deskripsi, foto_produk, modal, harga_jual, status, qty, tanggal_masuk, sumber_barang, keterangan_sumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "aktif", ?, ?, ?, ?)');
    $stmt->execute([
        $kode_item,
        $supplier_id,
        $merek,
        $kategori_id,
        $ukuran,
        $warna,
        $bahan,
        $kondisi,
        $deskripsi,
        $fotoPath,
        $modal,
        $harga_jual,
        $qty,
        $tanggal_masuk,
        $sumber_barang,
        $keterangan_sumber
    ]);

    flash('success', 'Produk berhasil ditambahkan.');
    redirect('produk/index.php');
}

$pageTitle = 'Tambah Produk';
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white pt-4 pb-3">
            <h4 class="mb-0"><i class="bi bi-box-seam text-primary me-2"></i>Tambah Produk Baru</h4>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <form method="POST" enctype="multipart/form-data" id="formTambah">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Section: Info Dasar -->
                <h6 class="text-muted text-uppercase fw-bold mb-3 border-bottom pb-2">Informasi Dasar</h6>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Kode Item</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="kode_item" id="kode_item" value="<?= generateKodeItem($pdo) ?>" readonly required>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()" title="Generate Ulang"><i class="bi bi-arrow-clockwise"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Merek <span class="fw-normal">(Opsional)</span></label>
                        <input type="text" name="merek" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Kategori</label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($kategori_list as $kat): ?>
                                <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Section: Spesifikasi -->
                <h6 class="text-muted text-uppercase fw-bold mb-3 mt-4 border-bottom pb-2">Spesifikasi Fisik</h6>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Ukuran</label>
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
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Warna</label>
                        <input type="text" name="warna" class="form-control" placeholder="Contoh: Hitam, Navy" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Bahan</label>
                        <input type="text" name="bahan" class="form-control" placeholder="Contoh: Katun, Denim">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted d-block">Kondisi <i class="bi bi-info-circle text-info" title="A=Sangat Baik, B=Baik, C=Cukup"></i></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="kondisi" id="kondisiA" value="A" autocomplete="off" required>
                            <label class="btn btn-outline-success" for="kondisiA">A</label>
                            
                            <input type="radio" class="btn-check" name="kondisi" id="kondisiB" value="B" autocomplete="off">
                            <label class="btn btn-outline-warning" for="kondisiB">B</label>
                            
                            <input type="radio" class="btn-check" name="kondisi" id="kondisiC" value="C" autocomplete="off">
                            <label class="btn btn-outline-danger" for="kondisiC">C</label>
                        </div>
                    </div>
                </div>

                <!-- Section: Media & Deskripsi -->
                <h6 class="text-muted text-uppercase fw-bold mb-3 mt-4 border-bottom pb-2">Media & Deskripsi</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Foto Produk (JPG/PNG, Max 2MB)</label>
                        <input type="file" name="foto_produk" id="foto_produk" class="form-control" accept="image/jpeg, image/png">
                        <div class="mt-2 text-center bg-white border rounded p-2" style="min-height: 150px; display: flex; align-items: center; justify-content: center;">
                            <img id="previewFoto" src="#" alt="Preview" class="img-fluid" style="display:none; max-height:150px;">
                            <span id="previewText" class="text-muted small">Preview Foto</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Deskripsi Detail</label>
                        <textarea name="deskripsi" class="form-control" rows="6" placeholder="Tuliskan catatan kondisi detail, cacat minor (jika ada), dsb..."></textarea>
                    </div>
                </div>

                <!-- Section: Harga & Sumber -->
                <h6 class="text-muted text-uppercase fw-bold mb-3 mt-4 border-bottom pb-2">Harga & Sumber Barang</h6>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Tanggal Masuk</label>
                        <input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Supplier Dasar</label>
                        <select name="supplier_id" id="supplier_id" class="form-select" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" data-modal="<?= $sup['modal_per_item'] ?>">
                                    <?= htmlspecialchars($sup['nama_supplier']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Sumber Barang Spesifik</label>
                        <input type="text" name="sumber_barang" class="form-control" placeholder="Contoh: Bal Segel X, Thrift Pasar Y">
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Keterangan Sumber Tambahan</label>
                        <textarea name="keterangan_sumber" class="form-control" rows="2" placeholder="Informasi tambahan pengiriman/kurir..."></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Modal per Item (Rp)</label>
                        <input type="number" name="modal" id="modal" class="form-control" step="0.01" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Stok Awal (Qty)</label>
                        <input type="number" name="qty" id="qty" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" id="harga_jual" class="form-control text-success fw-bold" step="0.01" required>
                        <div class="mt-2 p-2 bg-white border rounded text-center">
                            <span class="text-muted small fw-bold">Estimasi Keuntungan:</span><br>
                            <span class="text-success fw-bold fs-5" id="kalkulasiUntung">Rp 0</span>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary opacity-25">
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-light px-4 border">Batal</a>
                    <button type="submit" class="btn btn-primary px-4" id="btnSubmit"><i class="bi bi-save me-2"></i>Simpan Produk</button>
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
        
        var el = document.getElementById('kalkulasiUntung');
        el.innerText = format;
        el.className = untung >= 0 ? 'text-success fw-bold fs-5' : 'text-danger fw-bold fs-5';
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
    document.getElementById('foto_produk').addEventListener('change', function(e) {
        var preview = document.getElementById('previewFoto');
        var text = document.getElementById('previewText');
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.style.display = 'block';
                preview.src = e.target.result;
                text.style.display = 'none';
            }
            reader.readAsDataURL(this.files[0]);
        } else {
            preview.style.display = 'none';
            text.style.display = 'block';
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
