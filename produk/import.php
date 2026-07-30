<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$pageTitle = 'Import Excel Produk';

// Ambil data untuk validasi
$kategoriList = $pdo->query("SELECT id, nama_kategori FROM kategori_produk")->fetchAll(PDO::FETCH_KEY_PAIR);
$kategoriMap = array_map('strtolower', array_flip($kategoriList)); // strtolower(nama) => id

$suppliers = $pdo->query("SELECT id, nama_supplier FROM suppliers ORDER BY nama_supplier ASC")->fetchAll();

$importResult = null;
$errors = [];
$successCount = 0;
$failCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)$_POST['supplier_id'];
    $tanggal_masuk = $_POST['tanggal_masuk'] ?: date('Y-m-d');
    
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $fileExt = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'xlsx' && $fileExt !== 'xls') {
            $errors[] = "Format file harus .xlsx atau .xls";
        } else {
            try {
                $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
                
                // Mulai dari baris ke-2 (index 1) karena baris 1 adalah header
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    
                    // Skip baris kosong
                    if (empty(array_filter($row))) continue;
                    
                    $merek = sanitizeInput($row[0] ?? '');
                    if (empty($merek)) $merek = '-';
                    $kategori_nama = sanitizeInput($row[1] ?? '');
                    $ukuran = sanitizeInput($row[2] ?? '');
                    $kondisi = strtoupper(sanitizeInput($row[3] ?? ''));
                    $warna = sanitizeInput($row[4] ?? '');
                    $bahan = sanitizeInput($row[5] ?? '');
                    $modal = floatval($row[6] ?? 0);
                    $harga_jual = floatval($row[7] ?? 0);
                    $deskripsi = sanitizeInput($row[8] ?? '');
                    $sumber_barang = sanitizeInput($row[9] ?? '');
                    $keterangan_sumber = sanitizeInput($row[10] ?? '');
                    
                    $rowErrors = [];
                    
                    // if (empty($merek)) $rowErrors[] = "Merek kosong";
                    if (empty($ukuran)) $rowErrors[] = "Ukuran kosong";
                    if (!in_array($kondisi, ['A', 'B', 'C'])) $rowErrors[] = "Kondisi harus A, B, atau C";
                    if ($modal <= 0) $rowErrors[] = "Modal harus berupa angka > 0";
                    if ($harga_jual <= 0) $rowErrors[] = "Harga Jual harus berupa angka > 0";
                    
                    $kategori_id = null;
                    $kat_lower = strtolower($kategori_nama);
                    if (isset($kategoriMap[$kat_lower])) {
                        $kategori_id = $kategoriMap[$kat_lower];
                    } else {
                        $rowErrors[] = "Kategori '$kategori_nama' tidak valid";
                    }
                    
                    if (count($rowErrors) > 0) {
                        $failCount++;
                        $errors[] = "Baris " . ($i + 1) . " gagal: " . implode(", ", $rowErrors);
                    } else {
                        // Generate kode item baru
                        $kode_item = generateKodeItem($pdo);
                        
                        $stmt = $pdo->prepare('INSERT INTO produk (kode_item, supplier_id, merek, kategori_id, ukuran, warna, bahan, kondisi, deskripsi, modal, harga_jual, status, tanggal_masuk, sumber_barang, keterangan_sumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "di_rak", ?, ?, ?)');
                        $stmt->execute([
                            $kode_item, $supplier_id, $merek, $kategori_id, $ukuran, $warna, $bahan, $kondisi, $deskripsi, $modal, $harga_jual, $tanggal_masuk, $sumber_barang, $keterangan_sumber
                        ]);
                        $successCount++;
                    }
                }
                
                $importResult = true;
                
            } catch (Exception $e) {
                $errors[] = "Error membaca file: " . $e->getMessage();
            }
        }
    } else {
        $errors[] = "Tidak ada file yang diupload atau terjadi kesalahan upload.";
    }
}

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5">
    <div class="card shadow-sm border-0 mx-auto" style="max-width: 800px;">
        <div class="card-header bg-white pt-4 pb-3 border-0 d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-file-earmark-excel text-success me-2"></i> Import Produk Massal</h4>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>
        
        <div class="card-body bg-light rounded-bottom">
            <?php if ($importResult !== null): ?>
                <div class="alert <?= $successCount > 0 ? 'alert-success' : 'alert-warning' ?> border-0 shadow-sm mb-4">
                    <h5 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i>Ringkasan Import</h5>
                    <p class="mb-1">Berhasil diimport: <strong><?= $successCount ?> produk</strong></p>
                    <p class="mb-0 text-danger">Gagal: <strong><?= $failCount ?> produk</strong></p>
                </div>
                
                <?php if (count($errors) > 0): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4">
                        <h6 class="fw-bold mb-2">Detail Error:</h6>
                        <ul class="mb-0 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="alert alert-info border-0 shadow-sm mb-4">
                <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i>Petunjuk Import</h6>
                <ol class="mb-2 small">
                    <li>Download template excel yang telah disediakan.</li>
                    <li>Isi data produk sesuai format pada template. <strong>Jangan mengubah header/baris pertama!</strong></li>
                    <li>Kolom Kategori harus persis sesuai dengan kategori yang ada di sistem Anda (contoh: Pakaian_Pria).</li>
                    <li>Kondisi hanya boleh diisi dengan huruf <strong>A</strong>, <strong>B</strong>, atau <strong>C</strong>.</li>
                    <li>Kolom Modal dan Harga Jual harus berupa angka tanpa titik/koma (contoh: 150000).</li>
                </ol>
                <a href="download_template.php" class="btn btn-sm btn-outline-info mt-2 fw-bold"><i class="bi bi-download me-1"></i> Download Template Excel</a>
            </div>

            <form method="POST" enctype="multipart/form-data" class="bg-white p-4 rounded border shadow-sm" id="formImport">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">Pilih Supplier Dasar (Untuk batch ini)</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nama_supplier']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">Tanggal Masuk</label>
                        <input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small">Upload File Excel (.xlsx / .xls)</label>
                    <input type="file" name="excel_file" id="excel_file" class="form-control" accept=".xlsx, .xls" required>
                </div>

                <hr class="border-secondary opacity-25">
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-light px-4 border">Batal</a>
                    <button type="submit" class="btn btn-success px-4" id="btnSubmit"><i class="bi bi-upload me-2"></i>Mulai Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('formImport').addEventListener('submit', function() {
        var btn = document.getElementById('btnSubmit');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
        btn.disabled = true;
    });
</script>

<?php include '../includes/footer.php'; ?>
