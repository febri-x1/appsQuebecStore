<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (current_user()['role'] !== 'pemilik') {
    die('Akses ditolak');
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->execute([$id]);
$produk = $stmt->fetch();

if (!$produk) {
    die('Produk tidak ditemukan.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Label - <?= htmlspecialchars($produk['kode_item']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
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
            margin-bottom: 20px;
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
            margin-bottom: 20px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
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
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">🖨️ Cetak Label (6x4 cm)</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">Tutup</button>
    </div>

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

    <script>
        JsBarcode(".barcode").init();
    </script>
</body>
</html>
