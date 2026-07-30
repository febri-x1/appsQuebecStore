<?php
function rupiah($number)
{
    return CURRENCY_PREFIX . ' ' . number_format((float) $number, 0, ',', '.');
}

function formatRupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function clean($value)
{
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function sanitizeInput($input)
{
    return htmlspecialchars(trim((string) $input), ENT_QUOTES, 'UTF-8');
}

function formatTanggal($date)
{
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $timestamp = strtotime($date);
    return $hari[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $bulan[date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function redirect($path, $pesan = null, $tipe = 'success')
{
    if ($pesan !== null) {
        flash($tipe, $pesan);
    }
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function current_user()
{
    $active_role = $_SESSION['active_role'] ?? null;
    return $_SESSION['accounts'][$active_role] ?? null;
}

function flash($key, $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $value;
}

function next_invoice_number(PDO $pdo)
{
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT no_invoice FROM transaksi WHERE no_invoice LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $number = $last ? ((int) substr($last, -4)) + 1 : 1;

    return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
}

function generateKodeItem(PDO $pdo)
{
    $prefix = 'QS-' . date('Y') . '-';
    $stmt = $pdo->prepare("SELECT kode_item FROM produk WHERE kode_item LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $number = $last ? ((int) substr($last, -5)) + 1 : 1;

    return $prefix . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
}

