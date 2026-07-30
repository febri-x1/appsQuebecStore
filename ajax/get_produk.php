<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.kode_item, b.merek, kb.nama_kategori AS kategori, b.ukuran, b.warna,
               b.kondisi, b.foto_produk AS foto, b.modal, b.harga_jual, b.status,
               DATEDIFF(CURDATE(), b.tanggal_masuk) AS hari_di_rak,
               s.nama_supplier
        FROM produk b
        LEFT JOIN kategori_produk kb ON b.kategori_id = kb.id
        LEFT JOIN suppliers s ON s.id = b.supplier_id
        WHERE b.status = 'di_rak'
          AND (b.kode_item LIKE :q OR b.merek LIKE :q)
        ORDER BY b.tanggal_masuk ASC
        LIMIT 10
    ");
    
    $stmt->execute(['q' => "%$q%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [];
    foreach ($results as $row) {
        $row['potensi_keuntungan'] = (float)$row['harga_jual'] - (float)$row['modal'];
        $data[] = $row;
    }
    
    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
