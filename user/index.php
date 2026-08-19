<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ((current_user()['role'] ?? '') !== 'admin') {
    flash('error', 'Akses hanya untuk pemilik.');
    redirect('dashboard.php');
}

$pageTitle = 'Manajemen User';
$users = $pdo->query('SELECT id, nama, email, role, aktif, created_at FROM users ORDER BY nama ASC')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <a class="button" href="<?= BASE_URL; ?>/user/tambah.php">Tambah User</a>
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><?= clean($row['nama']); ?></td>
                <td><?= clean($row['email']); ?></td>
                <td><?= clean($row['role']); ?></td>
                <td>
                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: bold; background: <?= $row['aktif'] ? '#d1e7dd' : '#f8d7da'; ?>; color: <?= $row['aktif'] ? '#0f5132' : '#842029'; ?>;">
                        <?= $row['aktif'] ? 'Aktif' : 'Non-aktif'; ?>
                    </span>
                </td>
                <td><?= date('d/m/Y', strtotime($row['created_at'])); ?></td>
                <td class="actions">
                    <?php if ($row['id'] !== current_user()['id']): ?>
                        <a href="toggle_aktif.php?id=<?= $row['id']; ?>" class="<?= $row['aktif'] ? 'danger' : 'button'; ?>" style="<?= !$row['aktif'] ? 'background-color: #198754;' : ''; ?>" data-confirm="<?= $row['aktif'] ? 'Non-aktifkan' : 'Aktifkan'; ?> user ini?">
                            <?= $row['aktif'] ? 'Non-aktifkan' : 'Aktifkan'; ?>
                        </a>
                        <a class="danger" href="hapus.php?id=<?= $row['id']; ?>" data-confirm="Hapus user ini?">Hapus</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
