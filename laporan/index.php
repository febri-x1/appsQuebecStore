<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (!in_array(current_user()['role'], ['pemilik', 'admin', 'kasir'])) {
    flash('error', 'Akses ditolak. Anda tidak memiliki izin.');
    redirect('../index.php');
}

$pageTitle = 'Laporan Penjualan';

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
$whereClause = "WHERE tanggal_jual BETWEEN ? AND ?";

// 1. KPI Data
$kpi = safeExecute($pdo, "
    SELECT 
        COUNT(transaksi_id) as jml_trx,
        COALESCE(SUM(harga_jual), 0) as omzet,
        COALESCE(SUM(modal), 0) as modal,
        COALESCE(SUM(keuntungan), 0) as keuntungan
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    $whereClause
", $params)->fetch();

$jml_trx = $kpi['jml_trx'];
$omzet = $kpi['omzet'];
$modal = $kpi['modal'];
$keuntungan = $kpi['keuntungan'];
$avg_keuntungan = $jml_trx > 0 ? $keuntungan / $jml_trx : 0;

// 2. Chart Data: Daily Revenue
$dailyData = safeExecute($pdo, "
    SELECT tanggal_jual, SUM(harga_jual) as total 
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    $whereClause 
    GROUP BY tanggal_jual 
    ORDER BY tanggal_jual ASC
", $params)->fetchAll();
$chartDailyLabels = json_encode(array_column($dailyData, 'tanggal_jual'));
$chartDailyData = json_encode(array_column($dailyData, 'total'));

// 3. Chart Data: Profit by Category
$catData = safeExecute($pdo, "
    SELECT kategori, SUM(keuntungan) as profit 
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    $whereClause 
    GROUP BY kategori 
    ORDER BY profit DESC
", $params)->fetchAll();
$chartCatLabels = json_encode(array_column($catData, 'kategori'));
$chartCatData = json_encode(array_column($catData, 'profit'));

// 4. Chart Data: Payment Methods
$payData = safeExecute($pdo, "
    SELECT metode_bayar, COUNT(transaksi_id) as total 
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    $whereClause 
    GROUP BY metode_bayar
", $params)->fetchAll();
$chartPayLabels = json_encode(array_column($payData, 'metode_bayar'));
$chartPayData = json_encode(array_column($payData, 'total'));

// 5. Data Table: Recap Harian
$recapHarian = safeExecute($pdo, "
    SELECT 
        tanggal_jual, 
        COUNT(transaksi_id) as trx, 
        SUM(harga_jual) as omzet, 
        SUM(modal) as modal, 
        SUM(keuntungan) as keuntungan 
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    $whereClause 
    GROUP BY tanggal_jual 
    ORDER BY tanggal_jual DESC
", $params)->fetchAll();

// 6. Data Table: Recap Kasir
$recapKasir = safeExecute($pdo, "
    SELECT 
        nama_kasir, 
        COUNT(transaksi_id) as trx, 
        SUM(harga_jual) as omzet, 
        SUM(modal) as modal, 
        SUM(keuntungan) as keuntungan 
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    $whereClause 
    GROUP BY kasir_id, nama_kasir 
    ORDER BY omzet DESC
", $params)->fetchAll();

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid mt-4 mb-5" style="font-family: Arial, Helvetica, sans-serif;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-bar-chart-fill text-primary"></i> Laporan Penjualan</h2>
        <div class="d-flex gap-2">
            <a href="export_pdf.php?start=<?= $start ?>&end=<?= $end ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF Recap</a>
            <a href="export_excel.php?start=<?= $start ?>&end=<?= $end ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel Recap</a>
        </div>
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
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top border-secondary border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Total Modal</h6>
                    <h3 class="mb-0 text-secondary"><?= formatRupiah($modal) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top border-success border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Total Keuntungan</h6>
                    <h3 class="mb-0 text-success"><?= formatRupiah($keuntungan) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top border-info border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Jumlah Transaksi</h6>
                    <h3 class="mb-0 text-info"><?= number_format($jml_trx, 0, ',', '.') ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 border-top border-warning border-4 h-100 bg-white">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase small fw-bold">Rata-rata Untung / Trx</h6>
                    <h3 class="mb-0 text-warning"><?= formatRupiah($avg_keuntungan) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4 g-4">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-graph-up text-primary me-2"></i> Tren Pendapatan Harian</h6>
                </div>
                <div class="card-body" style="position: relative; height:300px;">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-pie-chart-fill text-success me-2"></i> Metode Pembayaran</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center" style="position: relative; height:300px;">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-5 g-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h6 class="fw-bold mb-0 text-muted"><i class="bi bi-bar-chart-steps text-warning me-2"></i> Keuntungan per Kategori Produk</h6>
                </div>
                <div class="card-body" style="position: relative; height:300px;">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables Toggle -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white pt-3 pb-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i> Rekapitulasi Data</h5>
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="tableToggle" id="btnHarian" autocomplete="off" checked onclick="showTable('harian')">
                <label class="btn btn-outline-primary btn-sm px-3" for="btnHarian">Rekap Harian</label>

                <input type="radio" class="btn-check" name="tableToggle" id="btnKasir" autocomplete="off" onclick="showTable('kasir')">
                <label class="btn btn-outline-primary btn-sm px-3" for="btnKasir">Rekap Per Kasir</label>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Table Harian -->
            <div id="tableHarian" class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Tanggal</th>
                            <th class="text-center">Jml Transaksi</th>
                            <th class="text-end">Total Omzet</th>
                            <th class="text-end">Total Modal</th>
                            <th class="text-end pe-4">Total Keuntungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($recapHarian) > 0): foreach($recapHarian as $r): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= formatTanggal($r['tanggal_jual']) ?></td>
                            <td class="text-center"><?= $r['trx'] ?></td>
                            <td class="text-end text-primary"><?= formatRupiah($r['omzet']) ?></td>
                            <td class="text-end text-muted"><?= formatRupiah($r['modal']) ?></td>
                            <td class="text-end pe-4 fw-bold text-success">+<?= formatRupiah($r['keuntungan']) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada data rekap harian</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Kasir -->
            <div id="tableKasir" class="table-responsive" style="display: none;">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nama Kasir</th>
                            <th class="text-center">Jml Transaksi</th>
                            <th class="text-end">Total Omzet</th>
                            <th class="text-end">Total Modal</th>
                            <th class="text-end pe-4">Total Keuntungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($recapKasir) > 0): foreach($recapKasir as $r): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><i class="bi bi-person-circle me-2 text-secondary"></i><?= htmlspecialchars($r['nama_kasir'] ?: 'Tidak diketahui') ?></td>
                            <td class="text-center"><?= $r['trx'] ?></td>
                            <td class="text-end text-primary"><?= formatRupiah($r['omzet']) ?></td>
                            <td class="text-end text-muted"><?= formatRupiah($r['modal']) ?></td>
                            <td class="text-end pe-4 fw-bold text-success">+<?= formatRupiah($r['keuntungan']) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada data rekap kasir</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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

    function showTable(type) {
        if (type === 'harian') {
            document.getElementById('tableHarian').style.display = 'block';
            document.getElementById('tableKasir').style.display = 'none';
        } else {
            document.getElementById('tableHarian').style.display = 'none';
            document.getElementById('tableKasir').style.display = 'block';
        }
    }

    // Chart.js Configuration
    Chart.defaults.font.family = 'Arial, sans-serif';
    Chart.defaults.color = '#6c757d';

    // 1. Line Chart: Daily Revenue
    const ctxLine = document.getElementById('lineChart').getContext('2d');
    new Chart(ctxLine, {
        type: 'line',
        data: {
            labels: <?= $chartDailyLabels ?>,
            datasets: [{
                label: 'Pendapatan Harian (Rp)',
                data: <?= $chartDailyData ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#0d6efd',
                pointBorderWidth: 2,
                pointRadius: 4,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f8f9fa' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Bar Chart: Profit by Category
    const ctxBar = document.getElementById('barChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?= $chartCatLabels ?>,
            datasets: [{
                label: 'Keuntungan (Rp)',
                data: <?= $chartCatData ?>,
                backgroundColor: '#ffc107',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f8f9fa' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 3. Pie Chart: Payment Methods
    const ctxPie = document.getElementById('pieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= $chartPayLabels ?>,
            datasets: [{
                data: <?= $chartPayData ?>,
                backgroundColor: ['#198754', '#0d6efd', '#0dcaf0', '#6c757d'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>