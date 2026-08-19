<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php'; // role: kasir
require_once 'includes/functions.php';

$user = current_user();
if (!$user || $user['role'] !== 'kasir') {
    redirect('dashboard.php', 'Akses ditolak. Halaman ini khusus untuk Kasir.', 'error');
}

$user_id = $user['id'];
$nama_kasir = $user['nama'];

// 1. Query Hari Ini (kasir login)
$stmtToday = $pdo->prepare("
    SELECT 
      COALESCE(SUM(qty), 0) AS jumlah_hari_ini,
      COALESCE(SUM(harga_jual * qty), 0) AS pendapatan_hari_ini,
      COALESCE(SUM(keuntungan * qty), 0) AS keuntungan_hari_ini
    FROM transaksi
    WHERE kasir_id = :kasir_id
      AND tanggal_jual = CURDATE()
");
$stmtToday->execute(['kasir_id' => $user_id]);
$today = $stmtToday->fetch(PDO::FETCH_ASSOC);

$jumlah_hari_ini = (int)($today['jumlah_hari_ini'] ?? 0);
$pendapatan_hari_ini = (float)($today['pendapatan_hari_ini'] ?? 0);
$keuntungan_hari_ini = (float)($today['keuntungan_hari_ini'] ?? 0);

// 2. Query Kemarin (untuk perbandingan)
$stmtYesterday = $pdo->prepare("
    SELECT 
      COALESCE(SUM(qty), 0) AS jumlah_kemarin,
      COALESCE(SUM(harga_jual * qty), 0) AS pendapatan_kemarin
    FROM transaksi
    WHERE kasir_id = :kasir_id
      AND tanggal_jual = CURDATE() - INTERVAL 1 DAY
");
$stmtYesterday->execute(['kasir_id' => $user_id]);
$yesterday = $stmtYesterday->fetch(PDO::FETCH_ASSOC);

$jumlah_kemarin = (int)($yesterday['jumlah_kemarin'] ?? 0);
$pendapatan_kemarin = (float)($yesterday['pendapatan_kemarin'] ?? 0);

// 3. Query Riwayat Transaksi Terbaru Hari Ini
$stmtHistory = $pdo->prepare("
    SELECT t.id, t.created_at, b.kode_item, b.merek,
           b.ukuran, b.kondisi, t.qty, t.harga_jual,
           t.keuntungan, t.metode_bayar
    FROM transaksi t
    JOIN produk b ON b.id = t.produk_id
    WHERE t.kasir_id = :kasir_id
      AND t.tanggal_jual = CURDATE()
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmtHistory->execute(['kasir_id' => $user_id]);
$riwayat = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

// Logika sapaan dinamis berdasarkan jam
$hour = (int)date('H');
if ($hour >= 6 && $hour < 12) {
    $greeting = "Selamat Pagi";
} elseif ($hour >= 12 && $hour < 15) {
    $greeting = "Selamat Siang";
} elseif ($hour >= 15 && $hour < 19) {
    $greeting = "Selamat Sore";
} else {
    $greeting = "Selamat Malam";
}

// Logika perbandingan Item Terjual
$diff_item = $jumlah_hari_ini - $jumlah_kemarin;
if ($diff_item > 0) {
    $icon_item = '↑';
    $color_item = 'text-success';
} elseif ($diff_item < 0) {
    $icon_item = '↓';
    $color_item = 'text-danger';
} else {
    $icon_item = '→';
    $color_item = 'text-secondary';
}

// Logika perbandingan Pendapatan
$diff_pendapatan = $pendapatan_hari_ini - $pendapatan_kemarin;
if ($diff_pendapatan > 0) {
    $icon_pendapatan = '↑';
    $color_pendapatan = 'text-success';
    $sign_pendapatan = '+';
} elseif ($diff_pendapatan < 0) {
    $icon_pendapatan = '↓';
    $color_pendapatan = 'text-danger';
    $sign_pendapatan = '';
} else {
    $icon_pendapatan = '→';
    $color_pendapatan = 'text-secondary';
    $sign_pendapatan = '';
}

// Rata-rata keuntungan per item
$rata_rata_keuntungan = $jumlah_hari_ini > 0 ? ($keuntungan_hari_ini / $jumlah_hari_ini) : 0;

// Logika persentase progress bar
// Memastikan persentase maksimal 100% menggunakan min()
$pct_item = min(100, round(($jumlah_hari_ini / TARGET_TRANSAKSI_HARIAN) * 100));
$pct_uang = min(100, round(($pendapatan_hari_ini / TARGET_PENDAPATAN_HARIAN) * 100));

// Tentukan warna progress bar target penjualan item
if ($pct_item < 50) $bg_item = 'bg-danger';
elseif ($pct_item < 80) $bg_item = 'bg-warning';
elseif ($pct_item < 100) $bg_item = 'bg-info';
else $bg_item = 'bg-success';

// Tentukan warna progress bar target pendapatan
if ($pct_uang < 50) $bg_uang = 'bg-danger';
elseif ($pct_uang < 80) $bg_uang = 'bg-warning';
elseif ($pct_uang < 100) $bg_uang = 'bg-info';
else $bg_uang = 'bg-success';

// Logika status gabungan (mengambil nilai rata-rata progress)
$avg_progress = ($pct_item + $pct_uang) / 2;
if ($avg_progress < 50) {
    $status_msg = "Ayo semangat! Masih banyak yang bisa dijual hari ini 💪";
} elseif ($avg_progress < 80) {
    $status_msg = "Bagus! Terus pertahankan semangat! 🔥";
} elseif ($avg_progress < 100) {
    $status_msg = "Hampir tercapai! Sedikit lagi! ⭐";
} else {
    $status_msg = "🎉 Target hari ini tercapai! Kerja keras kamu luar biasa!";
}

$pageTitle = 'Dashboard Kasir';
include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
    /* Styling Dasar Kasir */
    body { font-family: Arial, Helvetica, sans-serif; background-color: #f8f9fa; }
    .content-wrapper { font-size: 15px; }
    .card-kpi { border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    
    /* Warna border-top khusus kartu KPI */
    .border-kpi-transaksi { border-top: 4px solid #0d6efd !important; }
    .border-kpi-pendapatan { border-top: 4px solid #198754 !important; }
    .border-kpi-keuntungan { border-top: 4px solid #6f42c1 !important; }

    /* Angka Utama di KPI */
    .kpi-value { font-size: 2rem; font-weight: bold; }

    /* Tombol Mulai Jual */
    .btn-jual-utama { 
        padding: 16px 32px; 
        font-size: 1.3rem; 
        font-weight: bold;
        width: 100%;
    }

    /* Animasi Progress Bar */
    .progress-bar {
        width: 0%; /* Diinisialisasi 0 untuk transisi */
        transition: width 1s ease-in-out;
    }

    /* Highlight Transaksi Baru (< 5 menit) */
    .row-baru { background-color: #d1e7dd !important; }

    /* Table Hover */
    .table-hover tbody tr:hover { background-color: #f1f8ff; }
</style>

<div class="container-fluid mt-4 mb-5 content-wrapper">
    
    <!-- Flash Messages -->
    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- BAGIAN 1: HEADER SAPAAN -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body text-center py-4">
            <h2 class="mb-2">👋 <?= $greeting ?>, <?= sanitizeInput($nama_kasir) ?>!</h2>
            <p class="text-muted mb-4 fs-5">
                <?= formatTanggal(date('Y-m-d')) ?> • <span class="text-success fw-bold"><i class="bi bi-circle-fill" style="font-size: 0.8rem;"></i> Toko Buka</span>
            </p>
            <a href="transaksi/jual.php" class="btn btn-success btn-lg btn-jual-utama shadow">
                <i class="bi bi-cart-plus-fill"></i> MULAI JUAL PRODUK
            </a>
        </div>
    </div>

    <!-- BAGIAN 2: 3 KARTU RINGKASAN HARI INI -->
    <div class="row mb-4 g-3">
        <!-- Kartu Transaksi -->
        <div class="col-md-4">
            <div class="card card-kpi border-kpi-transaksi h-100 p-3">
                <div class="text-muted text-uppercase fw-bold mb-2">🧾 Transaksi Hari Ini</div>
                <div class="mb-3">
                    <span class="kpi-value text-primary"><?= $jumlah_hari_ini ?></span> 
                    <span class="text-muted">item terjual</span>
                </div>
                <div class="mt-auto small border-top pt-2">
                    <span class="text-muted">Kemarin: <?= $jumlah_kemarin ?> item</span>
                    <span class="<?= $color_item ?> fw-bold ms-1"><?= $icon_item ?></span>
                </div>
            </div>
        </div>

        <!-- Kartu Pendapatan -->
        <div class="col-md-4">
            <div class="card card-kpi border-kpi-pendapatan h-100 p-3">
                <div class="text-muted text-uppercase fw-bold mb-2">💰 Total Pendapatan</div>
                <div class="mb-3">
                    <span class="kpi-value text-success"><?= formatRupiah($pendapatan_hari_ini) ?></span>
                </div>
                <div class="mt-auto small border-top pt-2 d-flex justify-content-between">
                    <span class="text-muted">Kemarin: <?= formatRupiah($pendapatan_kemarin) ?></span>
                    <span class="<?= $color_pendapatan ?> fw-bold ms-1">
                        <?= $icon_pendapatan ?> <?= $sign_pendapatan ?><?= formatRupiah(abs($diff_pendapatan)) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Kartu Keuntungan -->
        <div class="col-md-4">
            <div class="card card-kpi border-kpi-keuntungan h-100 p-3">
                <div class="text-muted text-uppercase fw-bold mb-2">✨ Total Keuntungan</div>
                <div class="mb-3">
                    <span class="kpi-value" style="color: #6f42c1;"><?= formatRupiah($keuntungan_hari_ini) ?></span>
                </div>
                <div class="mt-auto small border-top pt-2 text-muted">
                    Rata-rata/item: <strong><?= formatRupiah($rata_rata_keuntungan) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- BAGIAN 3: TARGET PENJUALAN HARIAN & PROGRESS BAR -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 text-dark"><i class="bi bi-bullseye text-danger"></i> Target Penjualan Hari Ini</h5>
        </div>
        <div class="card-body">
            
            <!-- Progress Item -->
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-1 fw-bold">
                    <span>Jumlah Item Terjual</span>
                    <span><?= $jumlah_hari_ini ?> / <?= TARGET_TRANSAKSI_HARIAN ?> item (<?= $pct_item ?>%)</span>
                </div>
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated <?= $bg_item ?>" 
                         role="progressbar" 
                         data-target="<?= $pct_item ?>%" 
                         aria-valuenow="<?= $pct_item ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php if($pct_item == 100): ?> ✅ TERCAPAI! <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Progress Pendapatan -->
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-1 fw-bold">
                    <span>Total Pendapatan</span>
                    <span><?= formatRupiah($pendapatan_hari_ini) ?> / <?= formatRupiah(TARGET_PENDAPATAN_HARIAN) ?> (<?= $pct_uang ?>%)</span>
                </div>
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated <?= $bg_uang ?>" 
                         role="progressbar" 
                         data-target="<?= $pct_uang ?>%" 
                         aria-valuenow="<?= $pct_uang ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php if($pct_uang == 100): ?> ✅ TERCAPAI! <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Status Motivasi -->
            <div class="alert alert-secondary mb-0 fw-bold border-start border-4 border-info">
                Status: <?= $status_msg ?>
            </div>

        </div>
    </div>

    <!-- BAGIAN 4: RIWAYAT TRANSAKSI TERBARU HARI INI -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark"><i class="bi bi-clock-history"></i> 10 Transaksi Terakhir Hari Ini</h5>
        </div>
        <div class="card-body p-0">
            
            <?php if (count($riwayat) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 14px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">No</th>
                                <th>Waktu</th>
                                <th>Kode Item</th>
                                <th>Merek</th>
                                <th>Uk</th>
                                <th>Kondisi</th>
                                <th>Qty</th>
                                <th>Harga Jual (Total)</th>
                                <th>Metode Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $now = time();
                            foreach ($riwayat as $r): 
                                // Cek apakah transaksi kurang dari 5 menit yang lalu
                                $trx_time = strtotime($r['created_at']);
                                $is_new = ($now - $trx_time) < (5 * 60);
                            ?>
                                <tr class="<?= $is_new ? 'row-baru' : '' ?>">
                                    <td class="ps-3"><?= $no++ ?></td>
                                    <td>
                                        <?= date('H:i', strtotime($r['created_at'])) ?>
                                        <?php if($is_new): ?>
                                            <span class="badge bg-danger ms-1" style="font-size:0.7rem;">Baru</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-secondary"><?= sanitizeInput($r['kode_item']) ?></td>
                                    <td><?= sanitizeInput($r['merek']) ?></td>
                                    <td><?= sanitizeInput($r['ukuran']) ?></td>
                                    <td>
                                        <?php if($r['kondisi'] == 'A'): ?>
                                            <span class="badge bg-success">A</span>
                                        <?php elseif($r['kondisi'] == 'B'): ?>
                                            <span class="badge bg-warning text-dark">B</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">C</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold text-primary"><?= htmlspecialchars($r['qty']) ?></td>
                                    <td class="fw-bold text-success"><?= formatRupiah($r['harga_jual'] * $r['qty']) ?></td>
                                    <td>
                                        <?php if($r['metode_bayar'] == 'tunai'): ?>
                                            <span class="badge bg-success">Tunai</span>
                                        <?php elseif($r['metode_bayar'] == 'qris'): ?>
                                            <span class="badge bg-primary">QRIS</span>
                                        <?php else: ?>
                                            <span class="badge" style="background-color:#6f42c1;">Transfer</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-light text-end border-top">
                    <a href="transaksi/riwayat_kasir.php" class="text-decoration-none fw-bold">Lihat semua riwayat hari ini <i class="bi bi-arrow-right"></i></a>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="display-1 text-muted mb-3">🛍️</div>
                    <h5>Belum ada transaksi hari ini</h5>
                    <p class="text-muted">Yuk mulai jual produk pertamamu hari ini!</p>
                    <a href="transaksi/jual.php" class="btn btn-primary mt-2">Jual Produk Sekarang</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <!-- Footer Keterangan Auto-Refresh -->
    <div class="text-end text-muted small mt-2">
        <i class="bi bi-arrow-repeat"></i> Diperbarui: <span id="lastUpdated"><?= date('H:i:s') ?></span> • Auto-refresh 60 detik
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Javascript Auto-Refresh (Halaman refresh setiap 60 detik) ---
    setTimeout(() => {
        location.reload();
    }, 60000);

    // --- Animasi CSS Progress Bar (Memanjang saat dimuat) ---
    document.addEventListener("DOMContentLoaded", function() {
        const progressBars = document.querySelectorAll('.progress-bar');
        
        // Timeout kecil agar transisi CSS terlihat saat render
        setTimeout(() => {
            progressBars.forEach(bar => {
                const targetWidth = bar.getAttribute('data-target');
                bar.style.width = targetWidth;
            });
        }, 100);
    });
</script>

<?php include 'includes/footer.php'; ?>