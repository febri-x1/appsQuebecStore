<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ((current_user()['role'] ?? '') !== 'admin') {
    flash('error', 'Akses hanya untuk admin.');
    redirect('dashboard.php');
}

$pageTitle = 'Manajemen User';
$users = $pdo->query('SELECT id, nama, username, role, created_at FROM users ORDER BY nama ASC')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <a class="button" href="<?= BASE_URL; ?>/user/tambah.php">Tambah User</a>
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Dibuat</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><?= clean($row['nama']); ?></td>
                <td><?= clean($row['username']); ?></td>
                <td><?= clean($row['role']); ?></td>
                <td><?= date('d/m/Y', strtotime($row['created_at'])); ?></td>
                <td class="actions">
                    <?php if ($row['id'] !== current_user()['id']): ?>
                        <a class="danger" href="hapus.php?id=<?= $row['id']; ?>" data-confirm="Hapus user ini?">Hapus</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
