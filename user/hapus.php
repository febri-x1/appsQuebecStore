<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ((current_user()['role'] ?? '') !== 'admin') {
    flash('error', 'Akses hanya untuk pemilik.');
    redirect('dashboard.php');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id === current_user()['id']) {
    flash('error', 'User aktif tidak bisa dihapus.');
    redirect('user/index.php');
}

try {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    flash('success', 'User berhasil dihapus.');
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        flash('error', 'User tidak dapat dihapus karena memiliki data terkait (seperti riwayat transaksi atau pengeluaran).');
    } else {
        flash('error', 'Gagal menghapus user: ' . $e->getMessage());
    }
}
redirect('user/index.php');
