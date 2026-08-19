<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Pastikan kasir tidak bisa akses
if (current_user()['role'] === 'kasir') {
    flash('error', 'Akses ditolak. Halaman ini bukan untuk kasir.');
    redirect('dashboard_kasir.php');
}

$pageTitle = 'Manajemen Kategori';

// Ambil data kategori
$sql = "SELECT * FROM kategori_produk ORDER BY id DESC";
$kategoriList = $pdo->query($sql)->fetchAll();

include '../includes/header.php';
?>

<!-- Inject Bootstrap 5 CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container-fluid mt-4" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Manajemen Kategori</h2>
        <a href="tambah.php" class="btn btn-primary">+ Tambah Kategori</a>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="text-muted">Total: <?= count($kategoriList) ?> kategori</p>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($kategoriList) > 0): ?>
                            <?php $no = 1; foreach ($kategoriList as $k): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($k['nama_kategori']) ?></td>
                                    <td><?= htmlspecialchars($k['deskripsi'] ?? '-') ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit.php?id=<?= $k['id'] ?>" class="btn btn-warning text-dark">Edit</a>
                                            <button class="btn btn-danger" onclick="confirmDelete(<?= $k['id'] ?>)">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Data tidak ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus kategori ini? Kategori tidak dapat dihapus jika sudah digunakan di data produk.
            </div>
            <div class="modal-footer">
                <form method="POST" action="hapus.php" id="deleteForm">
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
