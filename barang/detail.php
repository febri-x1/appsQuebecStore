<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT b.*, kb.nama_kategori AS kategori, s.nama_supplier, s.harga_per_bal, t.tanggal_jual, t.harga_jual as harga_terjual, u.nama as nama_kasir
    FROM barang b 
    LEFT JOIN kategori_barang kb ON b.kategori_id = kb.id
    LEFT JOIN suppliers s ON b.supplier_id = s.id 
    LEFT JOIN transaksi t ON b.id = t.barang_id
    LEFT JOIN users u ON t.kasir_id = u.id
    WHERE b.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Barang tidak ditemukan.');
    redirect('barang/index.php');
}

$hari_di_rak = (strtotime(date('Y-m-d')) - strtotime($item['tanggal_masuk'])) / (60 * 60 * 24);
$is_deadstock = ($item['status'] === 'di_rak' && $hari_di_rak > 30);
$untung = $item['status'] === 'terjual' ? ($item['harga_terjual'] - $item['modal']) : ($item['harga_jual'] - $item['modal']);

$pageTitle = 'Detail Barang - ' . htmlspecialchars($item['kode_item']);
include '../includes/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Detail Barang: <?= htmlspecialchars($item['kode_item']) ?></h2>
        <div>
            <a href="index.php" class="btn btn-secondary">← Kembali ke Daftar</a>
            <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-warning">Edit</a>
            <?php if ($item['status'] !== 'terjual'): ?>
                <button class="btn btn-danger" onclick="confirmDelete(<?= $item['id'] ?>)">Hapus</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <?php if ($item['foto']): ?>
                        <img src="<?= BASE_URL . '/' . htmlspecialchars($item['foto']) ?>" class="img-fluid rounded" alt="Foto Barang" style="max-height: 350px;">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center rounded" style="height: 300px; color: #aaa;">
                            <span>Tidak ada foto</span>
                        </div>
                    <?php endif; ?>

                    <h5 class="mt-3"><?= htmlspecialchars($item['merek']) ?></h5>
                    <div class="mt-2">
                        <?php if ($item['status'] === 'di_rak'): ?>
                            <span class="badge bg-primary fs-6">Di Rak</span>
                            <?php if ($is_deadstock): ?>
                                <span class="badge bg-warning text-dark fs-6 mt-1">⚠ Deadstock (>30 Hari)</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark fs-6 mt-1"><?= $hari_di_rak ?> Hari di Rak</span>
                            <?php endif; ?>
                        <?php elseif ($item['status'] === 'terjual'): ?>
                            <span class="badge bg-secondary fs-6">Terjual</span>
                        <?php else: ?>
                            <span class="badge bg-danger fs-6">Rusak</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Informasi Barang</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th width="30%">Kategori</th>
                                <td><?= ucfirst(str_replace('_', ' ', $item['kategori'])) ?></td>
                            </tr>
                            <tr>
                                <th>Ukuran</th>
                                <td><?= htmlspecialchars($item['ukuran']) ?></td>
                            </tr>
                            <tr>
                                <th>Warna</th>
                                <td><?= htmlspecialchars($item['warna']) ?></td>
                            </tr>
                            <tr>
                                <th>Kondisi</th>
                                <td>
                                    <?php if ($item['kondisi'] == 'A') echo '<span class="badge bg-success">A (Sangat Baik)</span>';
                                    elseif ($item['kondisi'] == 'B') echo '<span class="badge bg-warning text-dark">B (Baik)</span>';
                                    else echo '<span class="badge bg-danger">C (Cukup)</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Deskripsi</th>
                                <td><?= nl2br(htmlspecialchars($item['deskripsi'] ?: '-')) ?></td>
                            </tr>
                            <tr>
                                <th>Tanggal Masuk</th>
                                <td><?= date('d M Y', strtotime($item['tanggal_masuk'])) ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4">Kalkulasi Keuangan</h5>
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th width="30%">Modal</th>
                                <td><?= formatRupiah($item['modal']) ?></td>
                            </tr>
                            <tr>
                                <th>Harga Jual (Target)</th>
                                <td><?= formatRupiah($item['harga_jual']) ?></td>
                            </tr>
                            <tr class="table-success">
                                <th><?= $item['status'] === 'terjual' ? 'Keuntungan Aktual' : 'Potensi Keuntungan' ?></th>
                                <td><strong><?= formatRupiah($untung) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4">Informasi Supplier</h5>
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th width="30%">Nama Supplier</th>
                                <td><?= htmlspecialchars($item['nama_supplier']) ?></td>
                            </tr>
                            <tr>
                                <th>Harga per Bal</th>
                                <td><?= formatRupiah($item['harga_per_bal']) ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <?php if ($item['status'] === 'terjual' && $item['tanggal_jual']): ?>
                        <h5 class="mt-4 text-primary">Informasi Transaksi</h5>
                        <table class="table table-bordered border-primary">
                            <tbody>
                                <tr>
                                    <th width="30%">Tanggal Jual</th>
                                    <td><?= date('d M Y', strtotime($item['tanggal_jual'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Harga Terjual</th>
                                    <td><?= formatRupiah($item['harga_terjual']) ?></td>
                                </tr>
                                <tr>
                                    <th>Kasir Pelayan</th>
                                    <td><?= htmlspecialchars($item['nama_kasir']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Yakin ingin menghapus barang ini? Data tidak dapat dikembalikan.
            </div>
            <div class="modal-footer">
                <form method="POST" action="hapus.php">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDelete(id) {
        document.getElementById('deleteId').value = id;
        var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        myModal.show();
    }
</script>
<?php include '../includes/footer.php'; ?>