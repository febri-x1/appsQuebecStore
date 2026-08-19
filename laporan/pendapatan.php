<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (!in_array(current_user()['role'], ['pemilik', 'admin'])) {
    flash('error', 'Akses ditolak. Anda tidak memiliki izin.');
    redirect('../index.php');
}

$pageTitle = 'Laporan Pendapatan';

// Filters
$filter_type = $_GET['filter_type'] ?? 'bulan_ini';
$custom_start = $_GET['start'] ?? '';
$custom_end = $_GET['end'] ?? '';

if ($filter_type === 'hari_ini') {
    $start = date('Y-m-d');
    $end = date('Y-m-d');
} elseif ($filter_type === 'minggu_ini') {
    $start = date('Y-m-d', strtotime('monday this week'));
    $end = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter_type === 'custom' && $custom_start && $custom_end) {
    $start = $custom_start;
    $end = $custom_end;
} else { // bulan_ini
    $start = date('Y-m-01');
    $end = date('Y-m-t');
}

$params = [$start, $end];
$whereClauseTrx = "WHERE tanggal_jual BETWEEN ? AND ?";
$whereClausePeng = "WHERE tanggal BETWEEN ? AND ?";

// 1. Data Transaksi (Pendapatan & Modal)
$kpiTrx = safeExecute($pdo, "
    SELECT 
        COALESCE(SUM(harga_jual), 0) as omzet,
        COALESCE(SUM(modal), 0) as modal,
        COALESCE(SUM(keuntungan), 0) as keuntungan
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    $whereClauseTrx
", $params)->fetch();

// 2. Data Pengeluaran
$kpiPeng = safeExecute($pdo, "
    SELECT COALESCE(SUM(nominal), 0) as total_pengeluaran 
    FROM pengeluaran 
    $whereClausePeng
", $params)->fetch();

$omzet = $kpiTrx['omzet'];
$modal = $kpiTrx['modal'];
$keuntungan = $kpiTrx['keuntungan'];
$pengeluaran = $kpiPeng['total_pengeluaran'];
$laba_bersih = $keuntungan - $pengeluaran;

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-wallet2 text-primary"></i> Laporan Pendapatan (Laba / Rugi)</h2>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body bg-light rounded">
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label text-muted fw-bold small">Pilih Periode</label>
                    <select name="filter_type" id="filter_type" class="form-select" onchange="toggleCustomDate()">
                        <option value="hari_ini" <?= $filter_type == 'hari_ini' ? 'selected' : '' ?>>Hari Ini</option>
                        <option value="minggu_ini" <?= $filter_type == 'minggu_ini' ? 'selected' : '' ?>>Minggu Ini</option>
                        <option value="bulan_ini" <?= $filter_type == 'bulan_ini' ? 'selected' : '' ?>>Bulan Ini</option>
                        <option value="custom" <?= $filter_type == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3 custom-date-group" style="display: <?= $filter_type == 'custom' ? 'block' : 'none' ?>;">
                    <label class="form-label text-muted fw-bold small">Dari Tanggal</label>
                    <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>">
                </div>
                <div class="col-md-3 custom-date-group" style="display: <?= $filter_type == 'custom' ? 'block' : 'none' ?>;">
                    <label class="form-label text-muted fw-bold small">Sampai Tanggal</label>
                    <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-primary mb-4 border-0 shadow-sm d-flex align-items-center">
        <i class="bi bi-calendar-check fs-4 me-3"></i>
        <div>
            <strong>Menampilkan Data:</strong> <?= formatTanggal($start) ?> s/d <?= formatTanggal($end) ?>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row mb-4 g-3">
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top border-primary border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Total Omzet</h6>
                    <h3 class="mb-0 text-primary"><?= formatRupiah($omzet) ?></h3>
                    <small class="text-muted">Pendapatan Kotor</small>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top border-secondary border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Total Modal</h6>
                    <h3 class="mb-0 text-secondary"><?= formatRupiah($modal) ?></h3>
                    <small class="text-muted">Harga Beli Produk Terjual</small>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top border-success border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Keuntungan (Laba Kotor)</h6>
                    <h3 class="mb-0 text-success"><?= formatRupiah($keuntungan) ?></h3>
                    <small class="text-muted">Omzet - Modal</small>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top border-danger border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Total Pengeluaran</h6>
                    <h3 class="mb-0 text-danger"><?= formatRupiah($pengeluaran) ?></h3>
                    <small class="text-muted">Biaya Operasional dsb</small>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top <?= $laba_bersih >= 0 ? 'border-info' : 'border-danger' ?> border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Laba Bersih</h6>
                    <h3 class="mb-0 <?= $laba_bersih >= 0 ? 'text-info' : 'text-danger' ?>"><?= formatRupiah($laba_bersih) ?></h3>
                    <small class="text-muted">Keuntungan - Pengeluaran</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white pt-3 pb-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-bar-graph me-2"></i> Ringkasan Pendapatan</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <td class="fw-bold w-50">Omzet (Pendapatan Kotor)</td>
                        <td class="text-end text-primary"><?= formatRupiah($omzet) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Total Modal Penjualan</td>
                        <td class="text-end text-secondary">- <?= formatRupiah($modal) ?></td>
                    </tr>
                    <tr class="table-light">
                        <td class="fw-bold text-success">Laba Kotor (Keuntungan Penjualan)</td>
                        <td class="text-end fw-bold text-success"><?= formatRupiah($keuntungan) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Total Pengeluaran (Biaya Operasional)</td>
                        <td class="text-end text-danger">- <?= formatRupiah($pengeluaran) ?></td>
                    </tr>
                    <tr class="<?= $laba_bersih >= 0 ? 'table-info' : 'table-danger' ?>">
                        <td class="fw-bold fs-5">Laba Bersih</td>
                        <td class="text-end fw-bold fs-5 <?= $laba_bersih >= 0 ? 'text-info' : 'text-danger' ?>"><?= formatRupiah($laba_bersih) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleCustomDate() {
        const type = document.getElementById('filter_type').value;
        const els = document.querySelectorAll('.custom-date-group');
        els.forEach(el => {
            el.style.display = type === 'custom' ? 'block' : 'none';
        });
    }
</script>

<?php include '../includes/footer.php'; ?>
