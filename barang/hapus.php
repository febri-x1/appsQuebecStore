<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php'; // hanya role 'pemilik'
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Metode tidak diizinkan.');
    redirect('barang/index.php');
}

$id = (int)($_POST['id'] ?? 0);

// Cek barang dan status
$stmt = $pdo->prepare("SELECT id, status, foto FROM barang WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Barang tidak ditemukan.');
    redirect('barang/index.php');
}

if ($item['status'] === 'terjual') {
    flash('error', 'Barang yang sudah terjual tidak dapat dihapus.');
    redirect('barang/index.php');
}

// Hapus foto jika ada
if (!empty($item['foto']) && file_exists('../' . $item['foto'])) {
    unlink('../' . $item['foto']);
}

// Hapus dari database
$delStmt = $pdo->prepare("DELETE FROM barang WHERE id = ? AND status != 'terjual'");
if ($delStmt->execute([$id])) {
    flash('success', 'Barang berhasil dihapus.');
} else {
    flash('error', 'Gagal menghapus barang.');
}

redirect('barang/index.php');

