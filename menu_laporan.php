<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Menu Laporan';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h2>Menu Laporan</h2>
        <p class="muted">Silakan pilih jenis laporan yang ingin Anda akses.</p>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">

            <!-- Laporan Semua Transaksi -->
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center;">
                <h3>Laporan Transaksi Lengkap</h3>
                <p class="muted">Menampilkan riwayat seluruh transaksi penjualan beserta rincian profit dan nama kasir.</p>
                <a href="laporan_transaksi.php" class="button" style="display: inline-block; margin-top: 15px;">Lihat Transaksi</a>
            </div>

            <!-- Laporan Penjualan (Berdasarkan Tanggal) -->
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center;">
                <h3>Rekap Penjualan Berkala</h3>
                <p class="muted">Rekap penjualan dengan filter tanggal, total omzet, serta fitur ekspor Excel dan PDF.</p>
                <a href="laporan/index.php" class="button" style="display: inline-block; margin-top: 15px;">Rekap Harian/Bulanan</a>
            </div>

            <!-- Laporan Deadstock -->
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center;">
                <h3>Barang Deadstock</h3>
                <p class="muted">Daftar item pakaian yang sudah lama di rak (> 30 hari) dan butuh evaluasi promo/diskon.</p>
                <a href="deadstock.php" class="button" style="display: inline-block; margin-top: 15px; background-color: #dc3545; color: white; border: none;">Lihat Deadstock</a>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>