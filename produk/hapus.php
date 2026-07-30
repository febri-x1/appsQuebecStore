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
    redirect('produk/index.php');
}

$id = (int)($_POST['id'] ?? 0);

// Cek produk dan status
$stmt = $pdo->prepare("SELECT id, status, foto FROM produk WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Produk tidak ditemukan.');
    redirect('produk/index.php');
}

if ($item['status'] === 'terjual') {
    flash('error', 'Produk yang sudah terjual tidak dapat dihapus.');
    redirect('produk/index.php');
}

// Hapus foto jika ada
if (!empty($item['foto']) && file_exists('../' . $item['foto'])) {
    unlink('../' . $item['foto']);
}

// Hapus dari database
$delStmt = $pdo->prepare("DELETE FROM produk WHERE id = ? AND status != 'terjual'");
if ($delStmt->execute([$id])) {
    flash('success', 'Produk berhasil dihapus.');
} else {
    flash('error', 'Gagal menghapus produk.');
}

redirect('produk/index.php');

