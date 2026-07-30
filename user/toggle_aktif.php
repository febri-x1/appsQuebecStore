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
    flash('error', 'Tidak bisa mengubah status diri sendiri.');
    redirect('user/index.php');
}

$stmt = $pdo->prepare('SELECT aktif FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if ($user) {
    $newStatus = $user['aktif'] ? 0 : 1;
    $stmt = $pdo->prepare('UPDATE users SET aktif = ? WHERE id = ?');
    $stmt->execute([$newStatus, $id]);
    $msg = $newStatus ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.';
    flash('success', $msg);
} else {
    flash('error', 'User tidak ditemukan.');
}

redirect('user/index.php');
