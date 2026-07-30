<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Pastikan kasir tidak bisa akses
if (current_user()['role'] === 'kasir') {
    flash('error', 'Akses ditolak.');
    redirect('dashboard_kasir.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        flash('error', 'ID Kategori tidak valid.');
        redirect('kategori/index.php');
    }

    // Cek apakah kategori masih dipakai di tabel produk
    $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM produk WHERE kategori_id = ?");
    $stmtCek->execute([$id]);
    $count = $stmtCek->fetchColumn();

    if ($count > 0) {
        flash('error', "Gagal menghapus! Kategori ini masih digunakan oleh $count produk.");
        redirect('kategori/index.php');
    }

    try {
        $stmtDelete = $pdo->prepare("DELETE FROM kategori_produk WHERE id = ?");
        $stmtDelete->execute([$id]);
        flash('success', 'Kategori berhasil dihapus.');
    } catch (PDOException $e) {
        flash('error', 'Gagal menghapus kategori. Pastikan tidak ada data yang terhubung.');
    }

    redirect('kategori/index.php');
} else {
    // Jika diakses tidak dengan metode POST
    redirect('kategori/index.php');
}