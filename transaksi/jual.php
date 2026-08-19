<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Restrict access to kasir only
if (current_user()['role'] !== 'kasir') {
    flash('error', 'Hanya kasir yang diizinkan untuk mengakses halaman ini.');
    redirect('dashboard.php');
}

// Generate CSRF Token for frontend Fetch API
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil beberapa produk terbaru yang masih di rak untuk Quick Select
$stmtProduk = $pdo->query("
    SELECT b.id, b.kode_item, b.merek, kb.nama_kategori AS kategori, 
           b.ukuran, b.warna, b.kondisi, b.modal, b.harga_jual, b.qty,
           b.foto_produk AS foto, DATEDIFF(CURDATE(), b.tanggal_masuk) AS hari_di_rak, 
           s.nama_supplier
    FROM produk b
    JOIN kategori_produk kb ON b.kategori_id = kb.id
    JOIN suppliers s ON b.supplier_id = s.id
    WHERE b.qty > 0 AND b.status = 'aktif'
    ORDER BY b.tanggal_masuk DESC
    LIMIT 12
");
$produkTersedia = $stmtProduk->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Penjualan (POS)';
include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f8f9fa;
    }

    .pos-search-input {
        font-size: 1.25rem;
        padding: 15px;
        border: 2px solid #ced4da;
        border-radius: 8px;
    }

    .pos-search-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .suggestion-box {
        position: absolute;
        z-index: 1000;
        width: 100%;
        background: white;
        border: 1px solid #ddd;
        border-radius: 0 0 8px 8px;
        max-height: 300px;
        overflow-y: auto;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: none;
    }

    .suggestion-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
    }

    .suggestion-item:hover {
        background-color: #f1f8ff;
    }

    .card-info {
        border-left: 5px solid #6c757d;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .card-info.kondisi-A {
        border-left-color: #198754;
    }

    .card-info.kondisi-B {
        border-left-color: #ffc107;
    }

    .card-info.kondisi-C {
        border-left-color: #dc3545;
    }

    .price-display {
        font-size: 1.8rem;
        font-weight: bold;
        color: #198754;
    }

    .preview-box {
        background-color: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 15px;
    }

    .btn-pos-submit {
        font-size: 1.2rem;
        padding: 12px;
        font-weight: bold;
    }

    /* Layout styling for disabled right column */
    #formKonfirmasi {
        opacity: 0.5;
        pointer-events: none;
        transition: opacity 0.3s;
    }

    #formKonfirmasi.active {
        opacity: 1;
        pointer-events: auto;
    }
</style>

<div class="container-fluid mt-3 mb-5">
    <div id="globalAlert"></div>

    <div class="row">
        <!-- KOLOM KIRI: Pencarian -->
        <div class="col-md-7 mb-4">
            <div class="card shadow-sm mb-3">
                <div class="card-body position-relative">
                    <h5 class="mb-3 text-secondary"><i class="bi bi-search"></i> Cari Produk</h5>
                    <input type="text" id="inputCari" class="form-control pos-search-input" placeholder="Ketik kode item (QS-2025-00001) atau nama merek..." autocomplete="off" autofocus>

                    <!-- Suggestions Dropdown -->
                    <div id="suggestionBox" class="suggestion-box"></div>
                </div>
            </div>

            <!-- GRID PRODUK TERSEDIA (Quick Select) -->
            <div id="gridProdukTersedia" class="mb-4">
                <h5 class="mb-3 text-secondary"><i class="bi bi-grid"></i> Pilihan Cepat (Terbaru)</h5>
                <div class="row row-cols-2 row-cols-md-3 g-3">
                    <?php foreach ($produkTersedia as $p): 
                        $fotoUrl = !empty($p['foto']) ? '../' . $p['foto'] : '../assets/img/no-photo.png';
                        // Encode data agar bisa dilempar langsung ke fungsi JavaScript
                        $jsonData = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm product-card" style="cursor: pointer;" onclick="selectItem(<?= $jsonData ?>)">
                                <img src="<?= $fotoUrl ?>" class="card-img-top p-1 rounded" alt="<?= htmlspecialchars($p['merek']) ?>" style="height: 120px; object-fit: cover;" onerror="this.src='https://via.placeholder.com/150?text=No+Photo'">
                                <div class="card-body p-2 text-center border-top">
                                    <div class="text-truncate fw-bold" style="font-size: 0.9rem;" title="<?= htmlspecialchars($p['merek']) ?>"><?= htmlspecialchars($p['merek']) ?></div>
                                    <div class="text-muted mb-1" style="font-size: 0.75rem;"><?= htmlspecialchars($p['kode_item']) ?></div>
                                    <div class="text-success fw-bold"><?= formatRupiah($p['harga_jual']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($produkTersedia) == 0): ?>
                    <div class="alert alert-info text-center">Belum ada produk aktif dengan stok tersedia.</div>
                <?php endif; ?>
            </div>

            <!-- KARTU INFO PRODUK -->
            <div id="kartuInfo" style="display: none;">
                <div class="card card-info" id="cardInfoBorder">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-4 text-center mb-3 mb-sm-0">
                                <img id="infoFoto" src="" class="img-fluid rounded" style="max-height: 200px; object-fit: cover;" alt="Foto">
                            </div>
                            <div class="col-sm-8">
                                <h4 class="mb-1" id="infoKode">QS-XXX</h4>
                                <h5 class="text-primary mb-3" id="infoTitle">Merek • Kategori • Ukuran</h5>

                                <div class="row mb-3 small">
                                    <div class="col-6 mb-1"><strong>Kondisi:</strong> <span id="infoKondisiBadge" class="badge"></span></div>
                                    <div class="col-6 mb-1"><strong>Warna:</strong> <span id="infoWarna"></span></div>
                                    <div class="col-6 mb-1"><strong>Supplier:</strong> <span id="infoSupplier"></span></div>
                                    <div class="col-6 mb-1"><strong>Masuk:</strong> <span id="infoMasuk"></span></div>
                                </div>

                                <div class="p-3 bg-light rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>Stok Tersedia:</span>
                                        <span class="text-primary fw-bold" id="infoStok">0</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>Target Jual (Satuan):</span>
                                        <span class="price-display" id="infoHargaJual">Rp 0</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted">
                                        <span>Modal Item (Satuan):</span>
                                        <span id="infoModal">Rp 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: Form Jual -->
        <div class="col-md-5">
            <div class="card shadow-sm h-100" id="formKonfirmasi">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 text-center"><i class="bi bi-cart-check"></i> KONFIRMASI PENJUALAN</h5>
                </div>
                <div class="card-body p-4">
                    <form id="formPenjualan">
                        <input type="hidden" id="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" id="produk_id" name="produk_id">
                        <input type="hidden" id="modal_item" name="modal">

                        <div class="mb-3">
                            <label class="form-label text-muted">Produk Terpilih</label>
                            <input type="text" class="form-control bg-light fw-bold" id="formProdukNama" readonly placeholder="Belum ada produk dipilih">
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Qty Pembelian <span class="text-danger">*</span></label>
                                <input type="number" id="inputQty" class="form-control form-control-lg text-center" value="1" min="1" required>
                                <small class="text-muted d-block mt-1">Maks: <span id="maxQtyLabel">-</span></small>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Harga Jual Satuan (Rp) <span class="text-danger">*</span></label>
                                <input type="number" id="inputHargaJual" class="form-control form-control-lg text-end" required min="0" step="1000">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold d-block">Metode Pembayaran <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="metode_bayar" id="btnTunai" value="tunai" autocomplete="off" checked>
                                <label class="btn btn-outline-success" for="btnTunai"><i class="bi bi-cash"></i> Tunai</label>

                                <input type="radio" class="btn-check" name="metode_bayar" id="btnQris" value="qris" autocomplete="off" disabled>
                                <label class="btn btn-outline-primary opacity-50" for="btnQris" title="Sedang Perbaikan"><i class="bi bi-qr-code-scan"></i> QRIS (Disabled)</label>

                                <input type="radio" class="btn-check" name="metode_bayar" id="btnTransfer" value="transfer" autocomplete="off" disabled>
                                <label class="btn btn-outline-info opacity-50" for="btnTransfer" title="Sedang Perbaikan"><i class="bi bi-bank"></i> Transfer (Disabled)</label>
                            </div>
                        </div>

                        <!-- Kalkulator Kembalian (Khusus Tunai) -->
                        <div id="sectionTunai" class="mb-4 p-3 rounded border" style="background-color: #f1f8ff;">
                            <label class="form-label fw-bold">Uang Diterima (Rp)</label>
                            <input type="number" id="inputUangDiterima" class="form-control form-control-lg text-end mb-2" min="0" step="1000" placeholder="0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-secondary">Kembalian:</span>
                                <strong id="labelKembalian" class="fs-4 text-primary">Rp 0</strong>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted">Catatan Transaksi (Opsional)</label>
                            <textarea id="inputCatatan" class="form-control" rows="2" placeholder="Contoh: Diskon langganan, tanpa plastik, dll"></textarea>
                        </div>

                        <!-- Preview Box -->
                        <div class="preview-box mb-4">
                            <h6 class="text-secondary border-bottom pb-2 mb-3"><i class="bi bi-calculator"></i> Preview Transaksi (Total)</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Harga Jual</span>
                                <strong id="previewJual">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Total Modal</span>
                                <strong class="text-muted" id="previewModal">Rp 0</strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Total Keuntungan</span>
                                <strong id="previewUntung" class="fs-5">Rp 0</strong>
                            </div>
                            <div id="warningMargin" class="text-danger small mt-2 fw-bold" style="display:none;">
                                <i class="bi bi-exclamation-triangle"></i> Peringatan: Harga jual berada di bawah modal!
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 btn-pos-submit mb-2" id="btnProses">
                            <i class="bi bi-check-circle-fill"></i> PROSES PENJUALAN
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100" id="btnReset">
                            <i class="bi bi-arrow-counterclockwise"></i> Ganti Produk
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sukses -->
<div class="modal fade" id="modalSukses" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-body text-center p-5">
                <div class="text-success mb-3">
                    <i class="bi bi-check-circle-fill" style="font-size: 5rem;"></i>
                </div>
                <h3 class="mb-4">TRANSAKSI BERHASIL!</h3>

                <div class="bg-light rounded p-3 text-start mb-4">
                    <h5 class="mb-1" id="suksesNamaProduk">-</h5>
                    <p class="text-muted small mb-3 border-bottom pb-2" id="suksesKodeProduk">-</p>

                    <div class="d-flex justify-content-between mb-1">
                        <span>Terjual (Aktual):</span>
                        <strong class="text-success fs-5" id="suksesTerjual">-</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Metode Bayar:</span>
                        <strong id="suksesMetode">-</strong>
                    </div>
                    <div class="d-flex justify-content-between mt-2 border-top pt-2" id="rowSuksesKembalian">
                        <span class="text-muted">Kembalian:</span>
                        <strong class="text-primary fs-5" id="suksesKembalian">-</strong>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary btn-lg" onclick="jualLagi()"><i class="bi bi-cart-plus"></i> Jual Produk Lagi</button>
                    <button type="button" class="btn btn-outline-primary btn-lg" onclick="cetakStruk()"><i class="bi bi-printer"></i> Cetak Struk</button>
                    <a href="riwayat_kasir.php" class="btn btn-outline-secondary"><i class="bi bi-list-check"></i> Lihat Riwayat Hari Ini</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Utility Helpers ---
    const formatRp = (num) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(num);

    // --- Elements ---
    const inputCari = document.getElementById('inputCari');
    const suggestionBox = document.getElementById('suggestionBox');
    const kartuInfo = document.getElementById('kartuInfo');
    const formKonfirmasi = document.getElementById('formKonfirmasi');
    const alertBox = document.getElementById('globalAlert');

    let timeoutId;
    let currentItem = null;
    let currentTransaksiId = null;

    // --- 1. AJAX Pencarian Produk ---
    inputCari.addEventListener('input', function() {
        clearTimeout(timeoutId);
        const q = this.value.trim();

        if (q.length < 3) {
            suggestionBox.style.display = 'none';
            return;
        }

        timeoutId = setTimeout(() => {
            fetch(`../ajax/get_produk.php?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    suggestionBox.innerHTML = '';
                    if (data.length === 0) {
                        suggestionBox.innerHTML = '<div class="p-3 text-danger"><i class="bi bi-info-circle"></i> Tidak ada produk tersedia yang cocok.</div>';
                    } else {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            div.innerHTML = `
                            <strong>${item.kode_item}</strong> - ${item.merek} 
                            <span class="badge bg-secondary ms-2">${item.kategori.replace('_', ' ').toUpperCase()}</span>
                            <div class="small text-muted mt-1">Stok: ${item.qty} | Jual: ${formatRp(item.harga_jual)}</div>
                        `;
                            div.onclick = () => selectItem(item);
                            suggestionBox.appendChild(div);
                        });
                    }
                    suggestionBox.style.display = 'block';
                })
                .catch(err => console.error(err));
        }, 300);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') suggestionBox.style.display = 'none';
    });
    document.addEventListener('click', (e) => {
        if (!inputCari.contains(e.target) && !suggestionBox.contains(e.target)) suggestionBox.style.display = 'none';
    });

    // --- 2. Pemilihan Produk ---
    function selectItem(item) {
        currentItem = item;
        suggestionBox.style.display = 'none';
        inputCari.value = '';
        alertBox.innerHTML = '';

        document.getElementById('infoFoto').src = item.foto ? `../${item.foto}` : '../assets/img/no-photo.png';
        document.getElementById('infoFoto').onerror = function() {
            this.src = 'https://via.placeholder.com/200x200?text=No+Photo';
        };

        document.getElementById('infoKode').innerText = item.kode_item;
        document.getElementById('infoTitle').innerText = `${item.merek} • ${item.kategori.replace('_', ' ').toUpperCase()} • Ukuran ${item.ukuran}`;
        document.getElementById('infoWarna').innerText = item.warna;
        document.getElementById('infoSupplier').innerText = item.nama_supplier;
        document.getElementById('infoMasuk').innerText = `${item.hari_di_rak} hari lalu`;

        const badge = document.getElementById('infoKondisiBadge');
        badge.innerText = item.kondisi;
        badge.className = 'badge';
        const border = document.getElementById('cardInfoBorder');
        border.className = 'card card-info';

        if (item.kondisi === 'A') {
            badge.classList.add('bg-success');
            border.classList.add('kondisi-A');
        } else if (item.kondisi === 'B') {
            badge.classList.add('bg-warning', 'text-dark');
            border.classList.add('kondisi-B');
        } else {
            badge.classList.add('bg-danger');
            border.classList.add('kondisi-C');
        }

        document.getElementById('infoHargaJual').innerText = formatRp(item.harga_jual);
        document.getElementById('infoModal').innerText = formatRp(item.modal);
        document.getElementById('infoStok').innerText = item.qty;
        document.getElementById('maxQtyLabel').innerText = item.qty;

        kartuInfo.style.display = 'block';
        document.getElementById('gridProdukTersedia').style.display = 'none';

        document.getElementById('produk_id').value = item.id;
        document.getElementById('modal_item').value = item.modal;
        document.getElementById('formProdukNama').value = `${item.kode_item} - ${item.merek}`;

        const inputHarga = document.getElementById('inputHargaJual');
        inputHarga.value = item.harga_jual;
        
        const inputQty = document.getElementById('inputQty');
        inputQty.value = 1;
        inputQty.max = item.qty;
        inputQty.focus();

        formKonfirmasi.classList.add('active');
        updatePreview();
    }

    // --- 3. Realtime Preview Kalkulasi ---
    const inputHargaJual = document.getElementById('inputHargaJual');
    const inputQty = document.getElementById('inputQty');
    
    inputHargaJual.addEventListener('input', updatePreview);
    inputQty.addEventListener('input', () => {
        // limit by max
        if (currentItem && parseInt(inputQty.value) > parseInt(currentItem.qty)) {
            inputQty.value = currentItem.qty;
        }
        updatePreview();
        hitungKembalian();
    });

    function updatePreview() {
        if (!currentItem) return;

        const qty = parseInt(inputQty.value) || 1;
        const hjual = parseFloat(inputHargaJual.value) || 0;
        const modal = parseFloat(currentItem.modal);
        
        const totalJual = hjual * qty;
        const totalModal = modal * qty;
        const untung = totalJual - totalModal;

        document.getElementById('previewJual').innerText = formatRp(totalJual);
        document.getElementById('previewModal').innerText = formatRp(totalModal);

        const preUntung = document.getElementById('previewUntung');
        preUntung.innerText = formatRp(untung);

        const warning = document.getElementById('warningMargin');
        if (hjual < modal) {
            preUntung.className = 'fs-5 text-danger';
            warning.style.display = 'block';
        } else {
            preUntung.className = 'fs-5 text-success fw-bold';
            warning.style.display = 'none';
        }
    }

    // --- 3b. Kalkulator Kembalian ---
    const inputUangDiterima = document.getElementById('inputUangDiterima');
    const labelKembalian = document.getElementById('labelKembalian');
    const sectionTunai = document.getElementById('sectionTunai');
    const radioMetode = document.querySelectorAll('input[name="metode_bayar"]');

    function hitungKembalian() {
        const qty = parseInt(document.getElementById('inputQty').value) || 1;
        const hjual = parseFloat(inputHargaJual.value) || 0;
        const totalJual = hjual * qty;
        const uang = parseFloat(inputUangDiterima.value) || 0;
        const kembalian = uang - totalJual;

        if (uang > 0 && kembalian >= 0) {
            labelKembalian.innerText = formatRp(kembalian);
            labelKembalian.className = 'fs-4 text-primary';
        } else if (uang > 0 && kembalian < 0) {
            labelKembalian.innerText = "Uang Kurang!";
            labelKembalian.className = 'fs-4 text-danger';
        } else {
            labelKembalian.innerText = "Rp 0";
            labelKembalian.className = 'fs-4 text-primary';
        }
    }

    inputUangDiterima.addEventListener('input', hitungKembalian);
    inputHargaJual.addEventListener('input', hitungKembalian);

    radioMetode.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'tunai') {
                sectionTunai.style.display = 'block';
            } else {
                sectionTunai.style.display = 'none';
                inputUangDiterima.value = '';
                labelKembalian.innerText = 'Rp 0';
            }
        });
    });

    // --- 4. Tombol Ganti Produk (Reset Form) ---
    document.getElementById('btnReset').addEventListener('click', function() {
        currentItem = null;
        kartuInfo.style.display = 'none';
        document.getElementById('gridProdukTersedia').style.display = 'block';
        formKonfirmasi.classList.remove('active');
        document.getElementById('formPenjualan').reset();
        
        document.getElementById('labelKembalian').innerText = 'Rp 0';
        document.getElementById('sectionTunai').style.display = 'block';
        
        inputCari.focus();
    });

    // --- 5. Submit Form dengan Fetch API ---
    document.getElementById('formPenjualan').addEventListener('submit', function(e) {
        e.preventDefault();

        if (!currentItem) return;

        const btnProses = document.getElementById('btnProses');
        btnProses.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...';
        btnProses.disabled = true;

        const formData = new URLSearchParams();
        formData.append('csrf_token', document.getElementById('csrf_token').value);
        formData.append('produk_id', document.getElementById('produk_id').value);
        formData.append('qty', document.getElementById('inputQty').value);
        formData.append('harga_jual', document.getElementById('inputHargaJual').value);
        formData.append('metode_bayar', document.querySelector('input[name="metode_bayar"]:checked').value);
        formData.append('catatan', document.getElementById('inputCatatan').value);

        fetch('proses_jual.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData.toString()
            })
            .then(async res => {
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Terjadi kesalahan pada server');
                }
                return data;
            })
            .then(data => {
                // Tampilkan Modal Sukses
                currentTransaksiId = data.data.transaksi_id;
                document.getElementById('suksesNamaProduk').innerText = `${data.data.merek} - ${currentItem.kategori.replace('_', ' ').toUpperCase()} ${currentItem.ukuran}`;
                document.getElementById('suksesKodeProduk').innerText = data.data.kode_item;
                document.getElementById('suksesTerjual').innerText = `${data.data.qty}x @ ${formatRp(data.data.harga_jual)} = ${formatRp(data.data.harga_jual * data.data.qty)}`;
                document.getElementById('suksesMetode').innerText = data.data.metode_bayar.toUpperCase();

                if (data.data.metode_bayar === 'tunai') {
                    const qty = parseInt(data.data.qty) || 1;
                    const hjual = parseFloat(data.data.harga_jual) || 0;
                    const totalJual = qty * hjual;
                    const uang = parseFloat(document.getElementById('inputUangDiterima').value) || 0;
                    const kembalian = uang > totalJual ? uang - totalJual : 0;
                    document.getElementById('suksesKembalian').innerText = formatRp(kembalian);
                    document.getElementById('rowSuksesKembalian').style.display = 'flex';
                } else {
                    document.getElementById('rowSuksesKembalian').style.display = 'none';
                }

                var modal = new bootstrap.Modal(document.getElementById('modalSukses'));
                modal.show();
            })
            .catch(err => {
                alertBox.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="bi bi-x-octagon-fill"></i> Gagal Memproses:</strong> ${err.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
                window.scrollTo(0, 0);
            })
            .finally(() => {
                btnProses.innerHTML = '<i class="bi bi-check-circle-fill"></i> PROSES PENJUALAN';
                btnProses.disabled = false;
            });
    });

    // --- 6. Helper Action di Modal Sukses ---
    function jualLagi() {
        const modalEl = document.getElementById('modalSukses');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();

        document.getElementById('btnReset').click();
        alertBox.innerHTML = '';
    }

    // --- 7. Buka Jendela Cetak Struk ---
    function cetakStruk() {
        if (currentTransaksiId) {
            const width = 400;
            const height = 600;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            window.open(`cetak_struk.php?id=${currentTransaksiId}`, 'CetakStruk', `width=${width},height=${height},top=${top},left=${left}`);
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
