<?php
    session_start();
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/includes/functions.php';

    // -------------------------------------------------------
    // Handle: ?clear_session=1 — hapus semua sesi aktif
    // Berguna ketika user tidak bisa login karena sesi lama
    // -------------------------------------------------------
    if (isset($_GET['clear_session'])) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        session_start();
        flash('success', 'Semua sesi berhasil dihapus. Silakan login kembali.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    // -------------------------------------------------------
    // Cek apakah ada sesi aktif yang masih tersisa (sesi lama)
    // -------------------------------------------------------
    $activeAccounts  = $_SESSION['accounts'] ?? [];
    $hasStaleSessions = !empty($activeAccounts);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = safeExecute($pdo, 'SELECT * FROM users WHERE email = ? AND aktif = 1 LIMIT 1', [$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            // Jika role ini sudah ada di sesi — tunjukkan pesan jelas
            if (isset($_SESSION['accounts'][$user['role']])) {
                flash('error', 'Role "' . ucfirst($user['role']) . '" sudah aktif. Klik "Hapus Semua Sesi" untuk login ulang dari awal.');
            } else {
                session_regenerate_id(true);

                if (!isset($_SESSION['accounts'])) {
                    $_SESSION['accounts'] = [];
                }

                $_SESSION['accounts'][$user['role']] = [
                    'id'         => $user['id'],
                    'nama'       => $user['nama'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                    'login_time' => time()
                ];

                $_SESSION['active_role'] = $user['role'];
                $_SESSION['flash']['force_tab_role'] = $user['role'];

                $dashboardUrl = ($user['role'] === 'kasir')
                    ? BASE_URL . '/dashboard_kasir.php?switch_role=' . urlencode($user['role'])
                    : BASE_URL . '/dashboard.php?switch_role=' . urlencode($user['role']);

                header('Location: ' . $dashboardUrl);
                exit;
            }
        } else {
            flash('error', 'Email tidak ditemukan, belum aktif, atau password salah.');
        }
    }

    $pageTitle = 'Login';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= APP_NAME; ?></title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
    <style>
        .stale-session-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            color: #664d03;
        }
        .stale-session-box strong { display: block; margin-bottom: 6px; }
        .stale-session-box .role-pill {
            display: inline-block;
            background: #ffc107;
            color: #000;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 2px;
        }
        .btn-clear-session {
            display: block;
            width: 100%;
            margin-top: 10px;
            padding: 8px;
            background: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-clear-session:hover {
            background: #dc3545;
            color: #fff;
        }
    </style>
</head>

<body class="login-page">
    <form class="login-card" method="post">
        <h1 style="text-align: center;"><?= APP_NAME; ?></h1>

        <?php if ($success = flash('success')): ?>
            <div class="alert success"><?= clean($success); ?></div>
        <?php endif; ?>
        <?php if ($error = flash('error')): ?>
            <div class="alert error"><?= clean($error); ?></div>
        <?php endif; ?>

        <?php if ($hasStaleSessions): ?>
        <div class="stale-session-box">
            <strong>&#9888;&#65039; Sesi Lama Terdeteksi</strong>
            Sistem mendeteksi sesi yang masih aktif untuk role:
            <div style="margin: 6px 0;">
                <?php foreach (array_keys($activeAccounts) as $r): ?>
                    <span class="role-pill"><?= ucfirst(htmlspecialchars($r)) ?></span>
                <?php endforeach; ?>
            </div>
            Jika Anda tidak ingat pernah login atau ingin memulai dari awal, klik tombol di bawah:
            <a href="<?= BASE_URL ?>/login.php?clear_session=1" class="btn-clear-session">
                &#128465;&#65039; Hapus Semua Sesi &amp; Login Ulang
            </a>
        </div>
        <?php endif; ?>

        <label>Email<input type="email" name="email" required autofocus></label>
        <label>Password<input type="password" name="password" required></label>
        <button type="submit">Masuk</button>
    </form>
</body>

</html>