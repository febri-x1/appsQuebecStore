<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->execute([$id]);
$produk = $stmt->fetch();

if (!$produk) {
    flash('error', 'Produk tidak ditemukan.');
    redirect('produk/index.php');
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
    $status = sanitizeInput($_POST['status']);
    
    // Foto Handling
    $fotoPath = $produk['foto_produk'];
    if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['foto_produk']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['foto_produk']['size'] <= 2000000) {
            $fileName = uniqid() . '.' . $ext;
            $targetDir = '../assets/img/produk/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            if (move_uploaded_file($_FILES['foto_produk']['tmp_name'], $targetDir . $fileName)) {
                // Hapus foto lama jika ada
                if ($fotoPath && file_exists('../' . $fotoPath)) {
                    unlink('../' . $fotoPath);
                }
                $fotoPath = 'assets/img/produk/' . $fileName;
            }
        } else {
            flash('error', 'Foto tidak valid (Maks 2MB, hanya JPG/JPEG/PNG).');
            redirect('produk/edit.php?id=' . $id);
        }
    }

    // Riwayat Harga Handling
    if ($produk['harga_jual'] != $harga_jual || $produk['modal'] != $modal) {
        $ket = [];
        if ($produk['harga_jual'] != $harga_jual) {
            $ket[] = "Harga Jual diubah dari " . formatRupiah($produk['harga_jual']) . " menjadi " . formatRupiah($harga_jual);
        }
        if ($produk['modal'] != $modal) {
            $ket[] = "Modal diubah dari " . formatRupiah($produk['modal']) . " menjadi " . formatRupiah($modal);
        }
        
        $stmt_riwayat = $pdo->prepare("INSERT INTO riwayat_harga (produk_id, harga_lama, harga_baru, diubah_oleh, keterangan) VALUES (?, ?, ?, ?, ?)");
        $stmt_riwayat->execute([
            $id,
            $produk['harga_jual'],
            $harga_jual,
            current_user()['id'],
            implode(". ", $ket)
        ]);
    }

    $stmt = $pdo->prepare('UPDATE produk SET supplier_id=?, merek=?, kategori_id=?, ukuran=?, warna=?, bahan=?, kondisi=?, deskripsi=?, foto_produk=?, modal=?, harga_jual=?, status=?, sumber_barang=?, keterangan_sumber=? WHERE id=?');
    $stmt->execute([
        $supplier_id, $merek, $kategori_id, $ukuran, $warna, $bahan, $kondisi, $deskripsi, $fotoPath, $modal, $harga_jual, $status, $sumber_barang, $keterangan_sumber, $id
    ]);

    flash('success', 'Data produk berhasil diperbarui.');
    redirect('produk/index.php');
}

$pageTitle = 'Edit Produk';
include '../includes/header.php';

// Prepare data for UI
$ukuran_list = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
$is_ukuran_lain = !in_array($produk['ukuran'], $ukuran_list);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white pt-4 pb-3">
            <h4 class="mb-0"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Produk: <?= htmlspecialchars($produk['kode_item']) ?></h4>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <form method="POST" enctype="multipart/form-data" id="formEdit">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Section: Info Dasar -->
                <h6 class="text-muted text-uppercase fw-bold mb-3 border-bottom pb-2">Informasi Dasar</h6>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Kode Item</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($produk['kode_item']) ?>" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Merek <span class="fw-normal">(Opsional)</span></label>
                        <input type="text" name="merek" class="form-control" value="<?= htmlspecialchars($produk['merek']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Kategori</label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($kategori_list as $kat): ?>
                                <option value="<?= $kat['id'] ?>" <?= $produk['kategori_id'] == $kat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
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
                            <?php foreach($ukuran_list as $uk): ?>
                                <option value="<?= $uk ?>" <?= $produk['ukuran'] == $uk ? 'selected' : '' ?>><?= $uk ?></option>
                            <?php endforeach; ?>
                            <option value="Lainnya" <?= $is_ukuran_lain ? 'selected' : '' ?>>Lainnya...</option>
                        </select>
                        <input type="text" name="ukuran_manual" id="ukuran_manual" class="form-control mt-2" placeholder="Isi manual" value="<?= $is_ukuran_lain ? htmlspecialchars($produk['ukuran']) : '' ?>" style="display:<?= $is_ukuran_lain ? 'block' : 'none' ?>;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Warna</label>
                        <input type="text" name="warna" class="form-control" value="<?= htmlspecialchars($produk['warna']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Bahan</label>
                        <input type="text" name="bahan" class="form-control" value="<?= htmlspecialchars($produk['bahan'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted d-block">Kondisi <i class="bi bi-info-circle text-info" title="A=Sangat Baik, B=Baik, C=Cukup"></i></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="kondisi" id="kondisiA" value="A" <?= $produk['kondisi'] == 'A' ? 'checked' : '' ?> required>
                            <label class="btn btn-outline-success" for="kondisiA">A</label>
                            
                            <input type="radio" class="btn-check" name="kondisi" id="kondisiB" value="B" <?= $produk['kondisi'] == 'B' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-warning" for="kondisiB">B</label>
                            
                            <input type="radio" class="btn-check" name="kondisi" id="kondisiC" value="C" <?= $produk['kondisi'] == 'C' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-danger" for="kondisiC">C</label>
                        </div>
                    </div>
                </div>

                <!-- Section: Media & Deskripsi -->
                <h6 class="text-muted text-uppercase fw-bold mb-3 mt-4 border-bottom pb-2">Media & Deskripsi</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Foto Produk (Biarkan kosong jika tidak diubah)</label>
                        <input type="file" name="foto_produk" id="foto_produk" class="form-control" accept="image/jpeg, image/png">
                        <div class="mt-2 text-center bg-white border rounded p-2" style="min-height: 150px; display: flex; align-items: center; justify-content: center; position: relative;">
                            <?php if ($produk['foto_produk']): ?>
                                <img id="previewFoto" src="<?= BASE_URL . '/' . htmlspecialchars($produk['foto_produk']) ?>" alt="Preview" class="img-fluid" style="max-height:150px;">
                                <span id="previewText" class="text-muted small" style="display:none;">Preview Foto Baru</span>
                            <?php else: ?>
                                <img id="previewFoto" src="#" alt="Preview" class="img-fluid" style="display:none; max-height:150px;">
                                <span id="previewText" class="text-muted small">Belum ada foto</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Deskripsi Detail</label>
                        <textarea name="deskripsi" class="form-control" rows="6"><?= htmlspecialchars($produk['deskripsi'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Section: Harga & Sumber -->
                <h6 class="text-muted text-uppercase fw-bold mb-3 mt-4 border-bottom pb-2">Harga & Status</h6>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Status Produk</label>
                        <select name="status" class="form-select" required>
                            <option value="di_rak" <?= $produk['status'] == 'di_rak' ? 'selected' : '' ?>>Di Rak (Tersedia)</option>
                            <option value="terjual" <?= $produk['status'] == 'terjual' ? 'selected' : '' ?>>Terjual</option>
                            <option value="rusak" <?= $produk['status'] == 'rusak' ? 'selected' : '' ?>>Rusak / Reject</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Supplier Dasar</label>
                        <select name="supplier_id" id="supplier_id" class="form-select" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" <?= $produk['supplier_id'] == $sup['id'] ? 'selected' : '' ?> data-modal="<?= $sup['modal_per_item'] ?>">
                                    <?= htmlspecialchars($sup['nama_supplier']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Sumber Barang Spesifik</label>
                        <input type="text" name="sumber_barang" class="form-control" value="<?= htmlspecialchars($produk['sumber_barang'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Tanggal Masuk</label>
                        <input type="date" class="form-control text-muted" value="<?= htmlspecialchars($produk['tanggal_masuk']) ?>" disabled>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Keterangan Sumber Tambahan</label>
                        <textarea name="keterangan_sumber" class="form-control" rows="2"><?= htmlspecialchars($produk['keterangan_sumber'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Modal per Item (Rp)</label>
                        <input type="number" name="modal" id="modal" class="form-control" step="0.01" value="<?= htmlspecialchars($produk['modal']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" id="harga_jual" class="form-control text-success fw-bold" step="0.01" value="<?= htmlspecialchars($produk['harga_jual']) ?>" required>
                        <div class="mt-2 p-2 bg-white border rounded text-center">
                            <span class="text-muted small fw-bold">Estimasi Keuntungan:</span><br>
                            <span class="text-success fw-bold fs-5" id="kalkulasiUntung">Rp 0</span>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary opacity-25">
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-light px-4 border">Batal</a>
                    <button type="submit" class="btn btn-primary px-4" id="btnSubmit"><i class="bi bi-save me-2"></i>Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
    
    // Init untung on load
    hitungUntung();

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
                if(text) text.style.display = 'none';
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Loading state
    document.getElementById('formEdit').addEventListener('submit', function() {
        var btn = document.getElementById('btnSubmit');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...';
        btn.disabled = true;
    });
</script>

<?php include '../includes/footer.php'; ?>