<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'admin') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT b.*, kb.nama_kategori AS kategori, s.nama_supplier, s.harga_per_bal, t.tanggal_jual, t.harga_jual as harga_terjual, u.nama as nama_kasir
    FROM produk b 
    LEFT JOIN kategori_produk kb ON b.kategori_id = kb.id
    LEFT JOIN suppliers s ON b.supplier_id = s.id 
    LEFT JOIN transaksi t ON b.id = t.produk_id
    LEFT JOIN users u ON t.kasir_id = u.id
    WHERE b.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Produk tidak ditemukan.');
    redirect('produk/index.php');
}

$stmtRiwayat = $pdo->prepare("SELECT rh.*, u.nama as nama_pengubah FROM riwayat_harga rh JOIN users u ON rh.diubah_oleh = u.id WHERE rh.produk_id = ? ORDER BY rh.tanggal_ubah DESC");
$stmtRiwayat->execute([$id]);
$riwayat_harga = $stmtRiwayat->fetchAll();

$hari_di_rak = (strtotime(date('Y-m-d')) - strtotime($item['tanggal_masuk'])) / (60 * 60 * 24);
$is_deadstock = ($item['status'] === 'di_rak' && $hari_di_rak > 30);
$untung = $item['status'] === 'terjual' ? ($item['harga_terjual'] - $item['modal']) : ($item['harga_jual'] - $item['modal']);

$pageTitle = 'Detail Produk - ' . htmlspecialchars($item['kode_item']);
include '../includes/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-tag text-primary me-2"></i>Detail Produk: <?= htmlspecialchars($item['kode_item']) ?></h2>
        <div class="btn-group">
            <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
            <a href="cetak_label.php?id=<?= $item['id'] ?>" target="_blank" class="btn btn-dark"><i class="bi bi-upc-scan me-1"></i> Cetak Label</a>
            <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-warning text-dark"><i class="bi bi-pencil me-1"></i>Edit</a>
            <?php if ($item['status'] !== 'terjual'): ?>
                <button class="btn btn-danger" onclick="confirmDelete(<?= $item['id'] ?>)"><i class="bi bi-trash me-1"></i>Hapus</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center p-4">
                    <?php if ($item['foto_produk']): ?>
                        <img src="<?= BASE_URL . '/' . htmlspecialchars($item['foto_produk']) ?>" class="img-fluid rounded object-fit-cover shadow-sm mb-3" alt="Foto Produk" style="width:100%; max-height: 400px;">
                    <?php else: ?>
                        <div class="bg-light d-flex flex-column align-items-center justify-content-center rounded shadow-sm mb-3" style="width:100%; height: 300px; color: #aaa;">
                            <i class="bi bi-image fs-1 mb-2"></i>
                            <span>Tidak ada foto produk</span>
                        </div>
                    <?php endif; ?>

                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($item['merek']) ?></h4>
                    <p class="text-muted mb-2"><?= ucfirst(str_replace('_', ' ', $item['kategori'] ?? '-')) ?></p>
                    
                    <div class="mt-3">
                        <?php if ($item['status'] === 'di_rak'): ?>
                            <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill shadow-sm">Di Rak</span>
                            <?php if ($is_deadstock): ?>
                                <span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill shadow-sm mt-2">⚠ Deadstock (>30 Hari)</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark px-3 py-2 fs-6 rounded-pill shadow-sm mt-2"><?= $hari_di_rak ?> Hari di Rak</span>
                            <?php endif; ?>
                        <?php elseif ($item['status'] === 'terjual'): ?>
                            <span class="badge bg-secondary px-3 py-2 fs-6 rounded-pill shadow-sm"><i class="bi bi-check-circle me-1"></i>Terjual</span>
                        <?php else: ?>
                            <span class="badge bg-danger px-3 py-2 fs-6 rounded-pill shadow-sm">Rusak / Reject</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h5 class="fw-bold text-muted mb-0"><i class="bi bi-list-ul text-info me-2"></i>Informasi Spesifikasi</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <tbody>
                            <tr>
                                <th width="30%" class="text-muted">Ukuran</th>
                                <td class="fw-bold"><?= htmlspecialchars($item['ukuran']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Warna</th>
                                <td><?= htmlspecialchars($item['warna']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Bahan</th>
                                <td><?= htmlspecialchars($item['bahan'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Kondisi</th>
                                <td>
                                    <?php if ($item['kondisi'] == 'A') echo '<span class="badge bg-success" data-bs-toggle="tooltip" title="Tidak ada cacat, seperti baru">A (Sangat Baik)</span>';
                                    elseif ($item['kondisi'] == 'B') echo '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="Ada sedikit cacat minor, masih layak pakai">B (Baik)</span>';
                                    else echo '<span class="badge bg-danger" data-bs-toggle="tooltip" title="Ada cacat terlihat, harga lebih murah">C (Cukup)</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Tanggal Masuk</th>
                                <td><?= date('d M Y', strtotime($item['tanggal_masuk'])) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Deskripsi Fisik</th>
                                <td><div class="bg-light p-3 rounded text-secondary" style="white-space: pre-wrap; font-size: 0.95rem;"><?= htmlspecialchars($item['deskripsi'] ?: 'Tidak ada deskripsi.') ?></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white pt-3 pb-2 border-0">
                            <h5 class="fw-bold text-muted mb-0"><i class="bi bi-wallet2 text-success me-2"></i>Kalkulasi Keuangan</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Modal Awal</td>
                                    <td class="text-end fw-bold"><?= formatRupiah($item['modal']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Harga Jual (Target)</td>
                                    <td class="text-end fw-bold text-primary"><?= formatRupiah($item['harga_jual']) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2"><hr class="my-1"></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><?= $item['status'] === 'terjual' ? 'Keuntungan Aktual' : 'Potensi Keuntungan' ?></td>
                                    <td class="text-end fw-bold text-success fs-5"><?= formatRupiah($untung) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white pt-3 pb-2 border-0">
                            <h5 class="fw-bold text-muted mb-0"><i class="bi bi-box-seam text-warning me-2"></i>Informasi Sumber</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Supplier Dasar</td>
                                    <td class="text-end fw-bold"><?= htmlspecialchars($item['nama_supplier']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Sumber Barang</td>
                                    <td class="text-end fw-bold"><?= htmlspecialchars($item['sumber_barang'] ?: '-') ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <div class="small text-muted mb-1 mt-2">Keterangan Sumber:</div>
                                        <div class="bg-light p-2 rounded text-secondary" style="font-size: 0.85rem;"><?= htmlspecialchars($item['keterangan_sumber'] ?: '-') ?></div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Riwayat Harga -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h5 class="fw-bold text-muted mb-0"><i class="bi bi-clock-history text-secondary me-2"></i>Riwayat Perubahan Harga & Modal</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Tanggal Ubah</th>
                                    <th>Diubah Oleh</th>
                                    <th>Harga Lama</th>
                                    <th>Harga Baru</th>
                                    <th class="pe-4">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($riwayat_harga) > 0): ?>
                                    <?php foreach($riwayat_harga as $rh): ?>
                                    <tr>
                                        <td class="ps-4 text-muted small"><?= date('d/m/Y H:i', strtotime($rh['tanggal_ubah'])) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($rh['nama_pengubah']) ?></span></td>
                                        <td class="text-danger text-decoration-line-through"><?= formatRupiah($rh['harga_lama']) ?></td>
                                        <td class="text-success fw-bold"><?= formatRupiah($rh['harga_baru']) ?></td>
                                        <td class="pe-4 small text-muted text-wrap" style="min-width: 200px;"><?= htmlspecialchars($rh['keterangan']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat perubahan harga untuk produk ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($item['status'] === 'terjual' && $item['tanggal_jual']): ?>
                <div class="card shadow-sm border-0 border-start border-success border-4 mb-4 bg-success bg-opacity-10">
                    <div class="card-body">
                        <h5 class="text-success fw-bold mb-3"><i class="bi bi-check-circle-fill me-2"></i>Informasi Transaksi</h5>
                        <div class="row text-success">
                            <div class="col-md-4">
                                <div class="small fw-bold text-uppercase opacity-75">Tanggal Jual</div>
                                <div class="fs-5"><?= date('d M Y', strtotime($item['tanggal_jual'])) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="small fw-bold text-uppercase opacity-75">Harga Terjual</div>
                                <div class="fs-5 fw-bold"><?= formatRupiah($item['harga_terjual']) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="small fw-bold text-uppercase opacity-75">Kasir Pelayan</div>
                                <div class="fs-5"><?= htmlspecialchars($item['nama_kasir']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Yakin ingin menghapus produk ini? Data yang terhapus tidak dapat dikembalikan.
            </div>
            <div class="modal-footer border-0 pt-0">
                <form method="POST" action="hapus.php">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    function confirmDelete(id) {
        document.getElementById('deleteId').value = id;
        var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        myModal.show();
    }
</script>
<?php include '../includes/footer.php'; ?>
