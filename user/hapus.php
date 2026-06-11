<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ((current_user()['role'] ?? '') !== 'pemilik') {
    flash('error', 'Akses hanya untuk pemilik.');
    redirect('dashboard.php');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id === current_user()['id']) {
    flash('error', 'User aktif tidak bisa dihapus.');
    redirect('user/index.php');
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$id]);
flash('success', 'User berhasil dihapus.');
redirect('user/index.php');
