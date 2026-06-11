<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Restrict access to kasir only
if (current_user()['role'] !== 'kasir') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: Hanya kasir yang dapat melakukan transaksi.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// 1. Cek CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF tidak valid. Silakan muat ulang halaman.']);
    exit;
}

$barang_id = (int)($_POST['barang_id'] ?? 0);
$harga_jual_input = (float)($_POST['harga_jual'] ?? 0);
$metode_bayar = $_POST['metode_bayar'] ?? '';
$catatan = trim($_POST['catatan'] ?? '');
$kasir_id = current_user()['id'];
$nama_kasir = current_user()['nama'];

if (!$barang_id || $harga_jual_input <= 0 || !in_array($metode_bayar, ['tunai', 'qris', 'transfer'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data input tidak lengkap atau tidak valid.']);
    exit;
}

try {
    // Memulai database transaction
    $pdo->beginTransaction();

    // 3. Ambil data barang dan LOCK baris ini (FOR UPDATE)
    $stmtBarang = $pdo->prepare("SELECT id, kode_item, merek, modal, status FROM barang WHERE id = ? FOR UPDATE");
    $stmtBarang->execute([$barang_id]);
    $b = $stmtBarang->fetch(PDO::FETCH_ASSOC);

    if (!$b) {
        throw new Exception("Barang tidak ditemukan dalam database.");
    }

    // 4. Validasi status barang
    if ($b['status'] !== 'di_rak') {
        throw new Exception("Barang ini sudah tidak tersedia (status: {$b['status']}).");
    }

    $modal = (float)$b['modal'];
    $keuntungan = $harga_jual_input - $modal;

    // 5. Insert ke tabel transaksi
    $stmtTrx = $pdo->prepare("
        INSERT INTO transaksi (barang_id, kasir_id, harga_jual, modal, metode_bayar, catatan, tanggal_jual)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE())
    ");
    $stmtTrx->execute([$barang_id, $kasir_id, $harga_jual_input, $modal, $metode_bayar, $catatan]);
    $transaksi_id = $pdo->lastInsertId();

    // 6. Update status barang menjadi terjual
    $stmtUpdate = $pdo->prepare("UPDATE barang SET status = 'terjual', updated_at = NOW() WHERE id = ? AND status = 'di_rak'");
    $stmtUpdate->execute([$barang_id]);

    // Jika karena alasan tertentu row tidak terupdate (meski sudah dilock)
    if ($stmtUpdate->rowCount() === 0) {
        throw new Exception("Gagal mengamankan stok barang. Kemungkinan ada transaksi ganda.");
    }

    // Commit transaksi
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Transaksi berhasil disimpan',
        'data' => [
            'transaksi_id' => $transaksi_id,
            'merek' => $b['merek'],
            'kode_item' => $b['kode_item'],
            'harga_jual' => $harga_jual_input,
            'modal' => $modal,
            'keuntungan' => $keuntungan,
            'metode_bayar' => $metode_bayar,
            'kasir' => $nama_kasir
        ]
    ]);

} catch (Exception $e) {
    // Batalkan seluruh perubahan jika terjadi error
    $pdo->rollBack();
    
    // Tentukan HTTP status code, default 400 Bad Request untuk logic error
    http_response_code(400); 
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
