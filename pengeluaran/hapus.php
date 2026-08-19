<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

$user = current_user();
if (current_user()['role'] !== 'pemilik') {
    redirect(BASE_URL . '/dashboard_kasir.php');
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM pengeluaran WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = 'Data pengeluaran berhasil dihapus.';
    } catch (PDOException $e) {
        // ...
    }
}

redirect('index.php');
