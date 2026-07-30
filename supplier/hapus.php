<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$user = current_user();
if (!in_array($user['role'] ?? '', ['admin', 'pemilik'])) {
    flash('error', 'Akses ditolak.');
    redirect('dashboard.php');
}

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'ID tidak valid.'); redirect('supplier/index.php'); }

$supplier = safeExecute($pdo, "
    SELECT s.*, COUNT(p.id) AS jumlah_produk
    FROM suppliers s
    LEFT JOIN produk p ON p.supplier_id = s.id
    WHERE s.id = ?
    GROUP BY s.id
", [$id])->fetch();

if (!$supplier) { flash('error', 'Supplier tidak ditemukan.'); redirect('supplier/index.php'); }

if ((int) $supplier['jumlah_produk'] > 0) {
    flash('error', 'Supplier <strong>' . htmlspecialchars($supplier['nama_supplier']) . '</strong> tidak dapat dihapus karena masih memiliki ' . $supplier['jumlah_produk'] . ' produk terkait.');
    redirect('supplier/index.php');
}

try {
    safeExecute($pdo, "DELETE FROM suppliers WHERE id = ?", [$id]);
    flash('success', 'Supplier <strong>' . htmlspecialchars($supplier['nama_supplier']) . '</strong> berhasil dihapus.');
} catch (PDOException $e) {
    flash('error', 'Gagal menghapus: ' . $e->getMessage());
}

redirect('supplier/index.php');
