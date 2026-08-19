<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'admin') {
    die('Akses ditolak');
}

$status_filter = $_GET['status'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$kondisi_filter = $_GET['kondisi'] ?? '';
$min_harga = $_GET['min_harga'] ?? '';
$max_harga = $_GET['max_harga'] ?? '';
$ukuran_filter = $_GET['ukuran'] ?? '';
$warna_filter = $_GET['warna'] ?? '';
$sumber_filter = $_GET['sumber'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($status_filter !== '') { $where[] = "status = ?"; $params[] = $status_filter; }
if ($kategori_filter !== '') { $where[] = "kategori_id = ?"; $params[] = $kategori_filter; }
if ($kondisi_filter !== '') { $where[] = "kondisi = ?"; $params[] = $kondisi_filter; }
if ($min_harga !== '') { $where[] = "harga_jual >= ?"; $params[] = $min_harga; }
if ($max_harga !== '') { $where[] = "harga_jual <= ?"; $params[] = $max_harga; }
if ($ukuran_filter !== '') { $where[] = "ukuran = ?"; $params[] = $ukuran_filter; }
if ($warna_filter !== '') { $where[] = "warna LIKE ?"; $params[] = "%$warna_filter%"; }
if ($sumber_filter !== '') { $where[] = "sumber_barang LIKE ?"; $params[] = "%$sumber_filter%"; }
if ($search !== '') {
    $where[] = "(merek LIKE ? OR kode_item LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT * FROM produk $whereClause ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produkList = $stmt->fetchAll();

if (count($produkList) == 0) {
    die('Tidak ada produk yang sesuai dengan filter pencarian untuk dicetak.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Semua Label</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f0f0f0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        .label-container {
            width: 60mm;
            height: 40mm;
            background: white;
            border: 1px dashed #ccc;
            padding: 2mm;
            box-sizing: border-box;
            text-align: center;
            font-family: Arial, sans-serif;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .store-name {
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
        }
        .product-info {
            font-size: 7pt;
            line-height: 1.1;
        }
        .price {
            font-weight: bold;
            font-size: 9pt;
        }
        .barcode-container {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .barcode {
            max-width: 100%;
            max-height: 14mm;
            object-fit: contain;
        }
        
        .no-print {
            width: 100%;
            text-align: center;
            margin-bottom: 20px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                display: block;
            }
            .no-print {
                display: none;
            }
            .label-container {
                border: none;
                margin: 0;
                page-break-after: always;
            }
            @page {
                size: 60mm 40mm;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">🖨️ Cetak <?= count($produkList) ?> Label</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">Tutup</button>
    </div>

    <?php foreach ($produkList as $produk): ?>
    <div class="label-container">
        <div class="store-name">QUEBEC STORE</div>
        <div class="product-info">
            <?= htmlspecialchars($produk['merek']) ?> - <?= htmlspecialchars($produk['ukuran']) ?> (Kondisi: <?= htmlspecialchars($produk['kondisi']) ?>)
        </div>
        <div class="price">
            <?= formatRupiah($produk['harga_jual']) ?>
        </div>
        <div class="barcode-container">
            <svg class="barcode"
                jsbarcode-format="CODE128"
                jsbarcode-value="<?= htmlspecialchars($produk['kode_item']) ?>"
                jsbarcode-textmargin="0"
                jsbarcode-height="40"
                jsbarcode-displayvalue="true"
                jsbarcode-fontsize="10"
                jsbarcode-margin="0">
            </svg>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        JsBarcode(".barcode").init();
    </script>
</body>
</html>
