<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$user = current_user();
if (!in_array($user['role'] ?? '', ['admin', 'pemilik'])) {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'ID penerimaan tidak valid.');
    redirect('penerimaan/index.php');
}

// Cek apakah data ada
$check = safeExecute($pdo, "SELECT id_penerimaan, no_penerimaan FROM penerimaan_barang WHERE id_penerimaan = ?", [$id])->fetch();
if (!$check) {
    flash('error', 'Data penerimaan tidak ditemukan.');
    redirect('penerimaan/index.php');
}

try {
    safeExecute($pdo, "DELETE FROM penerimaan_barang WHERE id_penerimaan = ?", [$id]);
    flash('success', 'Data penerimaan <strong>' . htmlspecialchars($check['no_penerimaan']) . '</strong> berhasil dihapus.');
} catch (PDOException $e) {
    flash('error', 'Gagal menghapus data: ' . $e->getMessage());
}

redirect('penerimaan/index.php');
