<?php
session_start();

$role_to_logout = $_GET['role'] ?? ($_SESSION['active_role'] ?? null);

if ($role_to_logout && isset($_SESSION['accounts'][$role_to_logout])) {
    // Hapus hanya role ini dari session
    unset($_SESSION['accounts'][$role_to_logout]);
}

// Jika setelah dihapus ternyata array accounts kosong, hancurkan session secara total
if (empty($_SESSION['accounts'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
} else {
    // Jika masih ada akun lain yang nyangkut, alihkan active_role ke salah satu yang tersisa
    $_SESSION['active_role'] = array_key_first($_SESSION['accounts']);
}

require_once __DIR__ . '/config/constants.php';
header('Location: ' . BASE_URL . '/login.php');
exit;
