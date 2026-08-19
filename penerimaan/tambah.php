<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$user = current_user();
if (!in_array($user['role'] ?? '', ['admin', 'pemilik'])) {
    flash('error', 'Akses ditolak. Halaman ini hanya untuk admin/pemilik.');
    redirect('dashboard.php');
}

$pageTitle = 'Tambah Penerimaan Barang';

// Ambil semua supplier untuk dropdown
$supplierList = safeExecute($pdo, "
    SELECT id, nama_supplier, telepon, alamat, harga_per_bal, isi_per_bal, modal_per_item
    FROM suppliers
    ORDER BY nama_supplier ASC
", [])->fetchAll();

// Ambil semua produk (status aktif) beserta supplier_id
$produkAll = safeExecute($pdo, "
    SELECT p.id, p.kode_item, p.merek, p.ukuran, p.kondisi, p.supplier_id, kb.nama_kategori
    FROM produk p
    JOIN kategori_produk kb ON kb.id = p.kategori_id
    WHERE p.status = 'aktif'
    ORDER BY p.merek ASC, p.kode_item ASC
", [])->fetchAll();

// Generate nomor penerimaan
function generateNoPenerimaan(PDO $pdo): string {
    $prefix = 'PNR-' . date('Ymd') . '-';
    $last   = safeExecute($pdo, "SELECT no_penerimaan FROM penerimaan_barang WHERE no_penerimaan LIKE ? ORDER BY id_penerimaan DESC LIMIT 1", [$prefix . '%'])->fetchColumn();
    $number = $last ? ((int) substr($last, -4)) + 1 : 1;
    return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id    = (int) ($_POST['supplier_id'] ?? 0);
    $produk_id      = (int) ($_POST['produk_id'] ?? 0);
    $qty            = (int) ($_POST['qty'] ?? 0);
    $keterangan     = trim($_POST['keterangan'] ?? '');
    $tanggal_terima = $_POST['tanggal_terima'] ?? date('Y-m-d');
    $admin_id       = $user['id'];

    if ($supplier_id <= 0)   $errors[] = 'Supplier wajib dipilih.';
    if ($produk_id  <= 0)    $errors[] = 'Produk wajib dipilih.';
    if ($qty        <= 0)    $errors[] = 'Jumlah qty harus lebih dari 0.';
    if (empty($tanggal_terima)) $errors[] = 'Tanggal penerimaan wajib diisi.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $no_penerimaan = generateNoPenerimaan($pdo);
            safeExecute($pdo, "
                INSERT INTO penerimaan_barang (no_penerimaan, produk_id, supplier_id, qty, keterangan, admin_id, tanggal_terima)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$no_penerimaan, $produk_id, $supplier_id, $qty, $keterangan ?: null, $admin_id, $tanggal_terima]);

            safeExecute($pdo, "UPDATE produk SET qty = qty + ? WHERE id = ?", [$qty, $produk_id]);

            $pdo->commit();

            flash('success', "Penerimaan barang <strong>{$no_penerimaan}</strong> berhasil dicatat.");
            redirect('penerimaan/index.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

// Build JS data maps
$supplierMap = [];
foreach ($supplierList as $s) {
    $supplierMap[$s['id']] = $s;
}
$produkMap = [];
foreach ($produkAll as $p) {
    $produkMap[$p['id']] = $p;
}

// Grup produk per supplier untuk filter JS
$produkPerSupplier = [];
foreach ($produkAll as $p) {
    $produkPerSupplier[$p['supplier_id']][] = $p;
}

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif; max-width: 900px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="bi bi-box-arrow-in-down text-primary me-2"></i>Tambah Penerimaan Barang</h2>
            <p class="text-muted small mb-0">Catat penerimaan atau restok produk dari supplier.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Terdapat kesalahan:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-plus text-primary me-2"></i>Form Penerimaan Barang</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="" id="formPenerimaan">

                <!-- Nomor Penerimaan (readonly) -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted small text-uppercase">No. Penerimaan</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-hash text-primary"></i></span>
                        <input type="text" class="form-control bg-light fw-bold text-primary"
                               value="PNR-<?= date('Ymd') ?>-XXXX (Otomatis)" readonly>
                    </div>
                    <small class="text-muted">Nomor penerimaan akan digenerate otomatis oleh sistem.</small>
                </div>

                <hr class="my-3">

                <!-- STEP 1: Pilih Supplier -->
                <div class="mb-4">
                    <label class="form-label fw-semibold fs-6" for="supplier_id">
                        <span class="badge bg-primary me-2">1</span>
                        <i class="bi bi-truck text-primary me-1"></i>Pilih Supplier <span class="text-danger">*</span>
                    </label>
                    <select name="supplier_id" id="supplier_id" class="form-select form-select-lg" required>
                        <option value="">-- Pilih Supplier --</option>
                        <?php foreach ($supplierList as $s): ?>
                        <option value="<?= $s['id'] ?>"
                                <?= (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['nama_supplier']) ?>
                            <?= $s['alamat'] ? '— ' . htmlspecialchars($s['alamat']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Info Supplier (dinamis) -->
                    <div id="supplier-info" class="mt-3 p-3 rounded border border-primary-subtle bg-primary-subtle d-none">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="text-muted small fw-semibold">Supplier</div>
                                <div class="fw-bold" id="sup-nama">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small fw-semibold">Telepon</div>
                                <div id="sup-telepon">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small fw-semibold">Harga / Bal</div>
                                <div class="text-primary fw-bold" id="sup-harga">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small fw-semibold">Modal / Item</div>
                                <div class="text-success fw-bold" id="sup-modal">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Pilih Produk (difilter berdasarkan supplier) -->
                <div class="mb-4">
                    <label class="form-label fw-semibold fs-6" for="produk_id">
                        <span class="badge bg-primary me-2">2</span>
                        <i class="bi bi-box-seam text-primary me-1"></i>Pilih Produk <span class="text-danger">*</span>
                    </label>
                    <select name="produk_id" id="produk_id" class="form-select form-select-lg" required disabled>
                        <option value="">-- Pilih Supplier terlebih dahulu --</option>
                    </select>
                    <small class="text-muted" id="produk-hint">Pilih supplier terlebih dahulu untuk melihat daftar produk.</small>

                    <!-- Info Produk (dinamis) -->
                    <div id="produk-info" class="mt-3 p-3 rounded border border-info-subtle bg-info-subtle d-none">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="text-muted small fw-semibold">Kode Item</div>
                                <div class="fw-bold" id="info-kode">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small fw-semibold">Merek</div>
                                <div class="fw-bold" id="info-merek">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small fw-semibold">Kategori</div>
                                <div class="fw-bold" id="info-kategori">—</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small fw-semibold">Ukuran / Kondisi</div>
                                <div class="fw-bold" id="info-ukuran">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-4">
                    <!-- Tanggal -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="tanggal_terima">
                            <i class="bi bi-calendar-event text-primary me-1"></i>Tanggal Penerimaan <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="tanggal_terima" id="tanggal_terima" class="form-control form-control-lg"
                               value="<?= htmlspecialchars($_POST['tanggal_terima'] ?? date('Y-m-d')) ?>"
                               max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <!-- Qty -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="qty">
                            <i class="bi bi-stack text-primary me-1"></i>Jumlah Qty (Pcs) <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="qty" id="qty" class="form-control form-control-lg"
                               min="1" step="1" placeholder="Contoh: 10"
                               value="<?= htmlspecialchars($_POST['qty'] ?? '') ?>" required>
                        <small class="text-muted">Jumlah item/pcs yang diterima dari supplier.</small>
                    </div>
                </div>

                <!-- Keterangan -->
                <div class="mt-4 mb-4">
                    <label class="form-label fw-semibold" for="keterangan">
                        <i class="bi bi-chat-text text-primary me-1"></i>Keterangan
                    </label>
                    <textarea name="keterangan" id="keterangan" class="form-control" rows="3"
                              placeholder="Contoh: Restok dari Jakarta, kondisi baik semua..."><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                    <small class="text-muted">Opsional — catatan tambahan mengenai penerimaan ini.</small>
                </div>

                <!-- Tombol -->
                <div class="d-flex gap-3 pt-2 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-save2 me-2"></i>Simpan Penerimaan
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg px-4">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Data Maps dari PHP ─────────────────────────────────────────────────
const supplierData = <?= json_encode(array_values(array_map(fn($s) => [
    'id'           => $s['id'],
    'nama'         => $s['nama_supplier'],
    'telepon'      => $s['telepon'] ?? '—',
    'alamat'       => $s['alamat'] ?? '',
    'harga_per_bal'=> (float)$s['harga_per_bal'],
    'modal_per_item'=> (float)($s['modal_per_item'] ?? 0),
], $supplierList))) ?>;

const produkPerSupplier = <?= json_encode(array_map(fn($produkArr) => array_values(array_map(fn($p) => [
    'id'        => $p['id'],
    'kode'      => $p['kode_item'],
    'merek'     => $p['merek'],
    'kategori'  => $p['nama_kategori'],
    'ukuran'    => $p['ukuran'],
    'kondisi'   => $p['kondisi'],
], $produkArr)), $produkPerSupplier)) ?>;

const supplierMap = {};
supplierData.forEach(s => supplierMap[s.id] = s);

// ── Format Rupiah ──────────────────────────────────────────────────────
function rupiah(n) {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

// ── Supplier change handler ────────────────────────────────────────────
document.getElementById('supplier_id').addEventListener('change', function () {
    const sid  = parseInt(this.value);
    const info = document.getElementById('supplier-info');
    const selProduk = document.getElementById('produk_id');
    const hint  = document.getElementById('produk-hint');

    // Reset produk dropdown & info
    document.getElementById('produk-info').classList.add('d-none');

    if (!sid || !supplierMap[sid]) {
        info.classList.add('d-none');
        selProduk.innerHTML = '<option value="">-- Pilih Supplier terlebih dahulu --</option>';
        selProduk.disabled = true;
        hint.textContent = 'Pilih supplier terlebih dahulu untuk melihat daftar produk.';
        return;
    }

    // Tampilkan info supplier
    const s = supplierMap[sid];
    document.getElementById('sup-nama').textContent   = s.nama;
    document.getElementById('sup-telepon').textContent = s.telepon;
    document.getElementById('sup-harga').textContent  = rupiah(s.harga_per_bal);
    document.getElementById('sup-modal').textContent  = rupiah(s.modal_per_item);
    info.classList.remove('d-none');

    // Filter produk berdasarkan supplier
    const produkList = produkPerSupplier[sid] || [];
    selProduk.innerHTML = '';
    if (produkList.length === 0) {
        selProduk.innerHTML = '<option value="">Tidak ada produk dari supplier ini</option>';
        selProduk.disabled = true;
        hint.textContent = 'Tidak ada produk aktif dari supplier ini.';
    } else {
        selProduk.innerHTML = '<option value="">-- Pilih Produk --</option>';
        produkList.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.kode} — ${p.merek} | ${p.kategori} | Ukuran ${p.ukuran} | Kondisi ${p.kondisi}`;
            selProduk.appendChild(opt);
        });
        selProduk.disabled = false;
        hint.textContent = `${produkList.length} produk tersedia dari supplier ini.`;
    }
});

// ── Produk change handler ──────────────────────────────────────────────
document.getElementById('produk_id').addEventListener('change', function () {
    const sid  = parseInt(document.getElementById('supplier_id').value);
    const pid  = parseInt(this.value);
    const info = document.getElementById('produk-info');

    if (!pid || !sid) { info.classList.add('d-none'); return; }

    const produkList = produkPerSupplier[sid] || [];
    const produk = produkList.find(p => p.id === pid);
    if (produk) {
        document.getElementById('info-kode').textContent     = produk.kode;
        document.getElementById('info-merek').textContent    = produk.merek;
        document.getElementById('info-kategori').textContent = produk.kategori;
        document.getElementById('info-ukuran').textContent   = produk.ukuran + ' / Kondisi ' + produk.kondisi;
        info.classList.remove('d-none');
    } else {
        info.classList.add('d-none');
    }
});

// ── Re-trigger jika ada nilai POST (validasi gagal) ────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const supSel = document.getElementById('supplier_id');
    if (supSel.value) {
        supSel.dispatchEvent(new Event('change'));
        // Setelah produk ter-populate, set nilai produk_id jika ada
        setTimeout(() => {
            const postProduk = <?= json_encode((int)($_POST['produk_id'] ?? 0)) ?>;
            if (postProduk) {
                document.getElementById('produk_id').value = postProduk;
                document.getElementById('produk_id').dispatchEvent(new Event('change'));
            }
        }, 50);
    }
});
</script>
<?php include '../includes/footer.php'; ?>
