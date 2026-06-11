<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

// Validasi Autentikasi Tunggal (Satu Sesi Global)
if (!current_user()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

