<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

$user = current_user();
if (current_user()['role'] !== 'pemilik') {
    redirect(BASE_URL . '/dashboard_kasir.php');
}

$pageTitle = 'Tambah Pengeluaran';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $kategori = $_POST['kategori_pengeluaran'] ?? '';
    $nominal = $_POST['nominal'] ?? 0;
    $keterangan = $_POST['keterangan'] ?? '';
    $kasir_id = $user['id'];

    if (empty($kategori) || empty($nominal)) {
        flash('error', 'Kategori dan nominal wajib diisi.');
    } elseif (strtotime($tanggal) > strtotime(date('Y-m-d'))) {
        flash('error', 'Tanggal pengeluaran tidak boleh lebih dari hari ini.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO pengeluaran (tanggal, kategori_pengeluaran, nominal, keterangan, kasir_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tanggal, $kategori, $nominal, $keterangan, $kasir_id]);
            flash('success', 'Data pengeluaran berhasil ditambahkan.');
            redirect('pengeluaran/index.php');
        } catch (PDOException $e) {
            flash('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="page-actions">
    <a href="index.php" class="button ghost">&larr; Kembali</a>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-header">
        <h2>Form Tambah Pengeluaran</h2>
        <p class="muted">Silakan isi rincian biaya operasional di bawah ini.</p>
    </div>
    <div class="card-body">
        <form class="form-panel" method="POST" action="">
            <label>Tanggal
                <input type="date" name="tanggal" value="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" required>
            </label>

            <label>Kategori
                <select name="kategori_pengeluaran" required>
                    <option value="">-- Pilih Kategori --</option>
                    <option value="listrik">Listrik</option>
                    <option value="gaji">Gaji</option>
                    <option value="sewa">Sewa</option>
                    <option value="operasional">Operasional</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </label>

            <label>Nominal (Rp)
                <input type="number" name="nominal" min="0" step="100" required placeholder="Contoh: 50000">
            </label>

            <label>Keterangan
                <input type="text" name="keterangan" placeholder="Deskripsi pengeluaran...">
            </label>

            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="button">Simpan</button>
                <a href="index.php" class="button ghost" style="text-align: center; display: inline-flex; align-items: center;">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
