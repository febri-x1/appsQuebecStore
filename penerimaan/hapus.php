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
$check = safeExecute($pdo, "SELECT id_penerimaan, no_penerimaan, produk_id, qty FROM penerimaan_barang WHERE id_penerimaan = ?", [$id])->fetch();
if (!$check) {
    flash('error', 'Data penerimaan tidak ditemukan.');
    redirect('penerimaan/index.php');
}

try {
    $pdo->beginTransaction();
    safeExecute($pdo, "UPDATE produk SET qty = qty - ? WHERE id = ?", [$check['qty'], $check['produk_id']]);
    safeExecute($pdo, "DELETE FROM penerimaan_barang WHERE id_penerimaan = ?", [$id]);
    $pdo->commit();
    flash('success', 'Data penerimaan <strong>' . htmlspecialchars($check['no_penerimaan']) . '</strong> berhasil dihapus.');
} catch (PDOException $e) {
    $pdo->rollBack();
    flash('error', 'Gagal menghapus data: ' . $e->getMessage());
}

redirect('penerimaan/index.php');
