<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

// Validasi Autentikasi Tunggal (Satu Sesi Global)
// Cek apakah ada request pindah role antar tab (dari GET atau POST)
$switchRole = $_GET['switch_role'] ?? $_POST['switch_role'] ?? null;
if ($switchRole && isset($_SESSION['accounts'][$switchRole])) {
    $_SESSION['active_role'] = $switchRole;
}

if (!current_user()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

