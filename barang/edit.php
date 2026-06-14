<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM barang WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Barang tidak ditemukan.');
    redirect('barang/index.php');
}

if ($item['status'] === 'terjual' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('error', 'Barang yang sudah terjual tidak dapat diedit.');
    redirect("barang/edit.php?id=$id");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$suppliers = $pdo->query("SELECT id, nama_supplier, modal_per_item FROM suppliers ORDER BY nama_supplier ASC")->fetchAll();
$kategori_list = $pdo->query("SELECT id, nama_kategori FROM kategori_barang ORDER BY nama_kategori ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

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
    $status = sanitizeInput($_POST['status']);

    $fotoPath = $item['foto'];
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['foto']['size'] <= 2000000) {
            $fileName = uniqid() . '.' . $ext;
            $targetDir = '../assets/img/uploads/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetDir . $fileName)) {
                if ($fotoPath && file_exists('../' . $fotoPath)) {
                    unlink('../' . $fotoPath);
                }
                $fotoPath = 'assets/img/uploads/' . $fileName;
            }
        } else {
            flash('error', 'Foto tidak valid (Maks 2MB, hanya JPG/PNG/WEBP).');
            redirect("barang/edit.php?id=$id");
        }
    }

    $stmt = $pdo->prepare('UPDATE barang SET supplier_id=?, merek=?, kategori_id=?, ukuran=?, warna=?, kondisi=?, deskripsi=?, foto=?, modal=?, harga_jual=?, status=?, tanggal_masuk=? WHERE id=?');
    $stmt->execute([
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
        $status,
        $tanggal_masuk,
        $id
    ]);

    flash('success', 'Data barang berhasil diperbarui.');
    redirect("barang/detail.php?id=$id");
}

$pageTitle = 'Edit Barang';
include '../includes/header.php';
$isDisabled = $item['status'] === 'terjual' ? 'disabled' : '';
$stdSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
$isLainnya = !in_array($item['ukuran'], $stdSizes);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h4 class="mb-0">Edit Barang: <?= htmlspecialchars($item['kode_item']) ?></h4>
        </div>
        <div class="card-body">
            <?php if ($item['status'] === 'terjual'): ?>
                <div class="alert alert-warning">
                    <strong>Peringatan:</strong> Barang ini sudah berstatus <b>Terjual</b>. Semua atribut telah dikunci dan tidak dapat diubah untuk menjaga integritas riwayat transaksi.
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="formEdit">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Kode Item</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($item['kode_item']) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="form-select" required <?= $isDisabled ?>>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" data-modal="<?= $sup['modal_per_item'] ?>" <?= $item['supplier_id'] == $sup['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sup['nama_supplier']) ?> (Modal: <?= formatRupiah($sup['modal_per_item']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Merek</label>
                        <input type="text" name="merek" class="form-control" value="<?= htmlspecialchars($item['merek']) ?>" required <?= $isDisabled ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" class="form-select" required <?= $isDisabled ?>>
                            <?php foreach ($kategori_list as $kat): ?>
                                <option value="<?= $kat['id'] ?>" <?= $item['kategori_id'] == $kat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Warna</label>
                        <input type="text" name="warna" class="form-control" value="<?= htmlspecialchars($item['warna']) ?>" required <?= $isDisabled ?>>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Ukuran</label>
                        <select name="ukuran" id="ukuran" class="form-select" required <?= $isDisabled ?> onchange="toggleUkuran()">
                            <?php foreach ($stdSizes as $s): ?>
                                <option value="<?= $s ?>" <?= $item['ukuran'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                            <option value="Lainnya" <?= $isLainnya ? 'selected' : '' ?>>Lainnya...</option>
                        </select>
                        <input type="text" name="ukuran_manual" id="ukuran_manual" class="form-control mt-2" placeholder="Isi manual" value="<?= $isLainnya ? htmlspecialchars($item['ukuran']) : '' ?>" style="display: <?= $isLainnya ? 'block' : 'none' ?>;" <?= $isDisabled ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-block">Kondisi</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="kondisi" value="A" <?= $item['kondisi'] == 'A' ? 'checked' : '' ?> required <?= $isDisabled ?>>
                            <label class="form-check-label">A (Sangat Baik)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="kondisi" value="B" <?= $item['kondisi'] == 'B' ? 'checked' : '' ?> <?= $isDisabled ?>>
                            <label class="form-check-label">B (Baik)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="kondisi" value="C" <?= $item['kondisi'] == 'C' ? 'checked' : '' ?> <?= $isDisabled ?>>
                            <label class="form-check-label">C (Cukup)</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <?php if ($item['status'] === 'terjual'): ?>
                            <input type="text" class="form-control" value="Terjual" readonly>
                            <input type="hidden" name="status" value="terjual">
                        <?php else: ?>
                            <select name="status" class="form-select" required>
                                <option value="di_rak" <?= $item['status'] == 'di_rak' ? 'selected' : '' ?>>Di Rak</option>
                                <option value="rusak" <?= $item['status'] == 'rusak' ? 'selected' : '' ?>>Rusak</option>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Modal per Item (Rp)</label>
                        <input type="number" name="modal" id="modal" class="form-control" step="0.01" value="<?= $item['modal'] ?>" required <?= $isDisabled ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" id="harga_jual" class="form-control" step="0.01" value="<?= $item['harga_jual'] ?>" required <?= $isDisabled ?>>
                        <small class="text-success fw-bold mt-1 d-block" id="kalkulasiUntung">Keuntungan: Rp 0</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Deskripsi (Opsional)</label>
                        <textarea name="deskripsi" class="form-control" rows="3" <?= $isDisabled ?>><?= htmlspecialchars($item['deskripsi'] ?: '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Foto Barang</label><br>
                        <?php if ($item['foto']): ?>
                            <img src="<?= BASE_URL . '/' . htmlspecialchars($item['foto']) ?>" class="img-thumbnail mb-2" style="max-height: 100px;">
                        <?php endif; ?>
                        <input type="file" name="foto" id="foto" class="form-control" accept="image/*" <?= $isDisabled ?>>
                        <small class="text-muted">Biarkan kosong jika tidak ingin mengubah foto (Max 2MB).</small>
                        <img id="previewFoto" src="#" alt="Preview" class="img-thumbnail mt-2" style="display:none; max-height:100px;">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Masuk</label>
                        <input type="date" name="tanggal_masuk" class="form-control" value="<?= $item['tanggal_masuk'] ?>" required <?= $isDisabled ?>>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <?php if ($item['status'] !== 'terjual'): ?>
                        <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan Perubahan</button>
                    <?php endif; ?>
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
        document.getElementById('kalkulasiUntung').innerText = "Keuntungan: " + format;
    }
    document.getElementById('modal').addEventListener('input', hitungUntung);
    document.getElementById('harga_jual').addEventListener('input', hitungUntung);
    hitungUntung(); // init

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
    var btn = document.getElementById('btnSubmit');
    if (btn) {
        document.getElementById('formEdit').addEventListener('submit', function() {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...';
            btn.disabled = true;
        });
    }
</script>

<?php include '../includes/footer.php'; ?>