<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (!in_array(current_user()['role'], ['pemilik', 'admin'])) {
    die('Akses ditolak');
}

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$params = [$start, $end];

$stmtRecap = $pdo->prepare("
    SELECT 
        tanggal_jual, 
        COUNT(transaksi_id) as trx, 
        SUM(harga_jual) as omzet, 
        SUM(modal) as modal, 
        SUM(keuntungan) as keuntungan 
    FROM (SELECT t.id AS transaksi_id, t.tanggal_jual, t.created_at, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.kondisi, t.harga_jual, t.modal, t.keuntungan, t.metode_bayar, t.catatan, u.id AS kasir_id, u.nama AS nama_kasir, s.nama_supplier FROM transaksi t JOIN produk b ON b.id = t.produk_id JOIN kategori_produk kb ON kb.id = b.kategori_id JOIN users u ON u.id = t.kasir_id JOIN suppliers s ON s.id = b.supplier_id) AS v_laporan_transaksi 
    WHERE tanggal_jual BETWEEN ? AND ?
    GROUP BY tanggal_jual 
    ORDER BY tanggal_jual ASC
");
$stmtRecap->execute($params);
$recapData = $stmtRecap->fetchAll();

$totalTrx = 0;
$totalOmzet = 0;
$totalModal = 0;
$totalKeuntungan = 0;
foreach($recapData as $r) {
    $totalTrx += $r['trx'];
    $totalOmzet += $r['omzet'];
    $totalModal += $r['modal'];
    $totalKeuntungan += $r['keuntungan'];
}

$chartLabels = json_encode(array_column($recapData, 'tanggal_jual'));
$chartData = json_encode(array_column($recapData, 'omzet'));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Penjualan <?= htmlspecialchars($start) ?> sd <?= htmlspecialchars($end) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #fff; }
        .print-area { max-width: 900px; margin: 0 auto; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-area">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div>
                <h3 class="mb-0 fw-bold">QUEBEC STORE</h3>
                <div class="text-muted">Laporan Rekapitulasi Penjualan</div>
            </div>
            <div class="text-end">
                <div>Periode: <strong><?= formatTanggal($start) ?> s/d <?= formatTanggal($end) ?></strong></div>
                <div class="text-muted small">Dicetak: <?= date('d/m/Y H:i') ?></div>
            </div>
        </div>

        <div class="row mb-4 text-center">
            <div class="col"><div class="border p-2 rounded"><strong>Total Omzet</strong><br><?= formatRupiah($totalOmzet) ?></div></div>
            <div class="col"><div class="border p-2 rounded"><strong>Total Keuntungan</strong><br><?= formatRupiah($totalKeuntungan) ?></div></div>
            <div class="col"><div class="border p-2 rounded"><strong>Jml Transaksi</strong><br><?= $totalTrx ?></div></div>
        </div>

        <div class="mb-4">
            <h6 class="fw-bold">Grafik Pendapatan Harian</h6>
            <div style="height: 250px; width: 100%;">
                <canvas id="lineChart"></canvas>
            </div>
        </div>

        <h6 class="fw-bold">Rincian Rekap Harian</h6>
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th class="text-center">Trx</th>
                    <th class="text-end">Omzet</th>
                    <th class="text-end">Modal</th>
                    <th class="text-end">Keuntungan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recapData as $r): ?>
                <tr>
                    <td><?= formatTanggal($r['tanggal_jual']) ?></td>
                    <td class="text-center"><?= $r['trx'] ?></td>
                    <td class="text-end"><?= formatRupiah($r['omzet']) ?></td>
                    <td class="text-end"><?= formatRupiah($r['modal']) ?></td>
                    <td class="text-end"><?= formatRupiah($r['keuntungan']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td class="text-end">TOTAL</td>
                    <td class="text-center"><?= $totalTrx ?></td>
                    <td class="text-end"><?= formatRupiah($totalOmzet) ?></td>
                    <td class="text-end"><?= formatRupiah($totalModal) ?></td>
                    <td class="text-end"><?= formatRupiah($totalKeuntungan) ?></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary px-4">Cetak / Simpan PDF</button>
            <a href="index.php" class="btn btn-secondary px-4">Kembali</a>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('lineChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= $chartLabels ?>,
                datasets: [{
                    label: 'Pendapatan Harian',
                    data: <?= $chartData ?>,
                    borderColor: '#000',
                    borderWidth: 2,
                    pointRadius: 0,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    onComplete: function() {
                        // Opsi untuk otomatis print jika ingin (dihilangkan agar user bisa preview dulu)
                        // setTimeout(() => window.print(), 500); 
                    }
                },
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        // Auto print (uncomment if you want automatic printing)
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 800);
        });
    </script>
</body>
</html>
