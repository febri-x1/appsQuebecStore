<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    die("ID Transaksi tidak ditemukan.");
}

$id = (int)$_GET['id'];

// Ambil data transaksi beserta detail barang dan nama kasir
$stmt = $pdo->prepare("
    SELECT t.*, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, u.nama as nama_kasir
    FROM transaksi t
    JOIN barang b ON t.barang_id = b.id
    JOIN kategori_barang kb ON b.kategori_id = kb.id
    JOIN users u ON t.kasir_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$trx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trx) {
    die("Transaksi tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Transaksi #<?= $id ?></title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            margin: 0;
            padding: 10px;
            width: 58mm;
            /* Sesuaikan dengan ukuran thermal printer standar (58mm atau 80mm) */
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .bold {
            font-weight: bold;
        }

        .mt-1 {
            margin-top: 5px;
        }

        .mt-2 {
            margin-top: 10px;
        }

        .mb-1 {
            margin-bottom: 5px;
        }

        .mb-2 {
            margin-bottom: 10px;
        }

        hr {
            border-top: 1px dashed #000;
            border-bottom: none;
            border-left: none;
            border-right: none;
        }

        .tabel-struk {
            width: 100%;
            border-collapse: collapse;
        }

        .tabel-struk td {
            vertical-align: top;
        }

        @media print {
            .no-print {
                display: none;
            }
        }

        .btn-print {
            padding: 10px;
            background-color: #0d6efd;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 10px;
            font-family: Arial, sans-serif;
            font-weight: bold;
        }
    </style>
</head>

<body onload="window.print()">
    <button class="no-print btn-print" onclick="window.print()">Cetak Ulang</button>

    <div class="text-center mb-2">
        <h2 style="margin:0; font-size: 16px;"><?= APP_NAME ?></h2>
        <p style="margin: 2px 0; font-size: 10px;">Toko Pakaian Thrifting Berkualitas</p>
    </div>
    <hr>
    <table class="tabel-struk mt-1 mb-1">
        <tr>
            <td>Waktu</td>
            <td>:</td>
            <td><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></td>
        </tr>
        <tr>
            <td>Kasir</td>
            <td>:</td>
            <td><?= htmlspecialchars($trx['nama_kasir']) ?></td>
        </tr>
        <tr>
            <td>No. Trx</td>
            <td>:</td>
            <td>#<?= $trx['id'] ?></td>
        </tr>
    </table>
    <hr>
    <div class="mt-1 mb-1">
        <div class="bold"><?= htmlspecialchars($trx['merek']) ?> - <?= strtoupper(str_replace('_', ' ', $trx['kategori'])) ?> (<?= htmlspecialchars($trx['ukuran']) ?>)</div>
        <table class="tabel-struk" style="margin-top: 2px;">
            <tr>
                <td><?= htmlspecialchars($trx['kode_item']) ?></td>
                <td class="text-right"><?= number_format($trx['harga_jual'], 0, ',', '.') ?></td>
            </tr>
        </table>
    </div>
    <hr>
    <table class="tabel-struk mt-1 mb-1">
        <tr>
            <td class="bold">TOTAL</td>
            <td class="bold text-right">Rp <?= number_format($trx['harga_jual'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>Metode Bayar</td>
            <td class="text-right"><?= strtoupper($trx['metode_bayar']) ?></td>
        </tr>
    </table>
    <hr>
    <div class="text-center mt-2" style="font-size: 10px;">
        Terima kasih atas kunjungan Anda.<br>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.
    </div>
</body>

</html>