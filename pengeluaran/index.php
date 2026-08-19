<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

$user = current_user();
if (current_user()['role'] !== 'pemilik') {
    redirect(BASE_URL . '/dashboard_kasir.php');
}

$pageTitle = 'Manajemen Pengeluaran';

// Ambil data pengeluaran
$stmt = $pdo->query("SELECT p.*, u.nama as nama_kasir FROM pengeluaran p JOIN users u ON u.id = p.kasir_id ORDER BY p.tanggal DESC, p.id DESC");
$pengeluaran = $stmt->fetchAll();
?>

<?php require_once '../includes/header.php'; ?>

<p class="muted">Mencatat biaya operasional toko.</p>

<div class="page-actions">
    <a href="tambah.php" class="button">+ Tambah Pengeluaran</a>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th>Nominal</th>
                <th>Pencatat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($pengeluaran) > 0): ?>
                <?php $no = 1; foreach ($pengeluaran as $p): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= formatTanggal($p['tanggal']); ?></td>
                        <td><span style="padding: 4px 8px; background: var(--bg); border: 1px solid var(--line); border-radius: 4px; font-size: 0.85em; text-transform: capitalize;"><?= clean($p['kategori_pengeluaran']); ?></span></td>
                        <td><?= clean($p['keterangan']); ?></td>
                        <td><?= formatRupiah($p['nominal']); ?></td>
                        <td><?= clean($p['nama_kasir']); ?></td>
                        <td class="actions">
                            <a href="hapus.php?id=<?= $p['id']; ?>" class="danger" onclick="return confirm('Yakin ingin menghapus data ini?');">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="empty-state">Belum ada data pengeluaran.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
