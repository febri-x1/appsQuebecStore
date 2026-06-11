 <?php
    session_start();
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/includes/functions.php';

    if (current_user()) {
        redirect('dashboard.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND aktif = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            
            // Inisialisasi struktur multi-account jika belum ada
            if (!isset($_SESSION['accounts'])) {
                $_SESSION['accounts'] = [];
            }
            
            // Simpan atau timpa role ini, tanpa mengganggu role lain yang sedang aktif di tab lain
            $_SESSION['accounts'][$user['role']] = [
                'id' => $user['id'],
                'nama' => $user['nama'],
                'email' => $user['email'],
                'role' => $user['role'],
                'login_time' => time()
            ];
            
            // Jadikan role ini sebagai active_role secara default (untuk tab ini)
            $_SESSION['active_role'] = $user['role'];
            
            // Sinyal ke frontend (JavaScript) untuk mengupdate status di sessionStorage
            $_SESSION['flash']['force_tab_role'] = $user['role'];
            
            redirect('dashboard.php');
        }

        flash('error', 'Email tidak ditemukan, belum aktif, atau password salah.');
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
 </head>

 <body class="login-page">
     <form class="login-card" method="post">
         <h1><?= APP_NAME; ?></h1>
         <p>Masuk ke sistem kasir.</p>
         <?php if ($error = flash('error')): ?>
             <div class="alert error"><?= clean($error); ?></div>
         <?php endif; ?>
         <label>Email<input type="email" name="email" required autofocus></label>
         <label>Password<input type="password" name="password" required></label>
         <button type="submit">Masuk</button>
         <small>Demo: admin@quebec.com / admin123</small>
     </form>
 </body>

 </html>