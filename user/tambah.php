<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ((current_user()['role'] ?? '') !== 'admin') {
    flash('error', 'Akses hanya untuk pemilik.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        trim($_POST['nama']),
        trim($_POST['email']),
        password_hash($_POST['password'], PASSWORD_DEFAULT),
        $_POST['role'],
    ]);
    flash('success', 'User berhasil ditambahkan.');
    redirect('user/index.php');
}

$pageTitle = 'Tambah User';
include __DIR__ . '/../includes/header.php';
?>
<form class="form-panel" method="post">
    <label>Nama<input name="nama" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Password<input type="password" name="password" required></label>
    <label>Role
        <select name="role" required>
            <option value="kasir">Kasir</option>
            <option value="pemilik">Pemilik</option>
        </select>
    </label>
    <button type="submit">Simpan</button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
