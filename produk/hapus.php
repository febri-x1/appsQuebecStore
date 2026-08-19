<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php'; // hanya role 'pemilik'
require_once '../includes/functions.php';

if (current_user()['role'] !== 'admin') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Metode tidak diizinkan.');
    redirect('produk/index.php');
}

$id = (int)($_POST['id'] ?? 0);

// Cek produk dan status
$stmt = $pdo->prepare("SELECT id, status, foto_produk, qty FROM produk WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'Produk tidak ditemukan.');
    redirect('produk/index.php');
}

// Cek apakah produk pernah ditransaksikan
$cekTrx = $pdo->prepare("SELECT id FROM transaksi WHERE produk_id = ? LIMIT 1");
$cekTrx->execute([$id]);
if ($cekTrx->fetch()) {
    flash('error', 'Produk ini tidak dapat dihapus karena sudah memiliki riwayat transaksi.');
    redirect('produk/index.php');
}

// Hapus foto jika ada
if (!empty($item['foto_produk']) && file_exists('../' . $item['foto_produk'])) {
    unlink('../' . $item['foto_produk']);
}

// Hapus dari database
$delStmt = $pdo->prepare("DELETE FROM produk WHERE id = ?");
if ($delStmt->execute([$id])) {
    flash('success', 'Produk berhasil dihapus.');
} else {
    flash('error', 'Gagal menghapus produk.');
}

redirect('produk/index.php');

