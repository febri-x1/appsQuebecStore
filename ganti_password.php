<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Ganti Password';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        flash('error', 'Semua kolom wajib diisi.');
        redirect('ganti_password.php');
    }

    if ($password_baru !== $konfirmasi_password) {
        flash('error', 'Konfirmasi password baru tidak cocok.');
        redirect('ganti_password.php');
    }

    // Cek password lama
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $db_password = $stmt->fetchColumn();

    if (!password_verify($password_lama, $db_password)) {
        flash('error', 'Password lama tidak sesuai.');
        redirect('ganti_password.php');
    }

    // Update password baru
    $hashed_password = password_hash($password_baru, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $user['id']]);

    flash('success', 'Password berhasil diubah. Silakan gunakan password baru Anda untuk login selanjutnya.');
    redirect('ganti_password.php');
}

include __DIR__ . '/includes/header.php';
?>

<div style="max-width: 500px; margin: 0 auto;">
    <form class="form-panel" method="POST" action="">
        <h3 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-key-fill" style="color: #f59e0b;"></i> Ganti Password
        </h3>
        
        <label>
            Password Lama
            <input type="password" name="password_lama" required>
        </label>
        
        <label>
            Password Baru
            <input type="password" name="password_baru" required>
        </label>
        
        <label>
            Konfirmasi Password Baru
            <input type="password" name="konfirmasi_password" required>
        </label>
        
        <div class="actions" style="justify-content: flex-end; margin-top: 10px;">
            <button type="reset" class="secondary">Reset</button>
            <button type="submit">Ganti Password</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>