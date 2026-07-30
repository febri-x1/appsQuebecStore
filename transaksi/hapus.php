<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Metode tidak diizinkan.');
    redirect('transaksi/index.php');
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    flash('error', 'Token CSRF tidak valid.');
    redirect('transaksi/index.php');
}

$id = (int)($_POST['id'] ?? 0);

// Validasi transaksi ada dan dibuat hari ini
$stmt = $pdo->prepare("SELECT produk_id, created_at FROM transaksi WHERE id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t) {
    flash('error', 'Transaksi tidak ditemukan.');
    redirect('transaksi/index.php');
}

$isToday = (date('Y-m-d', strtotime($t['created_at'])) === date('Y-m-d'));
if (!$isToday) {
    flash('error', 'Hanya transaksi hari ini yang dapat dihapus.');
    redirect('transaksi/index.php');
}

try {
    $pdo->beginTransaction();

    // 1. Kembalikan status produk
    $stmtProduk = $pdo->prepare("UPDATE produk SET status = 'di_rak' WHERE id = ?");
    $stmtProduk->execute([$t['produk_id']]);

    // 2. Hapus transaksi
    $stmtDel = $pdo->prepare("DELETE FROM transaksi WHERE id = ?");
    $stmtDel->execute([$id]);

    $pdo->commit();
    flash('success', 'Transaksi berhasil dihapus dan status produk dikembalikan menjadi Di Rak.');
} catch (Exception $e) {
    $pdo->rollBack();
    flash('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
}

redirect('transaksi/index.php');
