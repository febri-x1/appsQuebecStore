<?php
require 'config/database.php';

function runSql($pdo, $sql) {
    try {
        $pdo->exec($sql);
        echo "SUCCESS: " . substr(trim($sql), 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . " on SQL: " . substr(trim($sql), 0, 50) . "...\n";
    }
}

// 1. Modifikasi tabel produk
runSql($pdo, "ALTER TABLE produk ADD COLUMN qty INT(11) NOT NULL DEFAULT 0 AFTER harga_jual");
runSql($pdo, "UPDATE produk SET qty = 1 WHERE status = 'di_rak'");
runSql($pdo, "UPDATE produk SET qty = 0 WHERE status != 'di_rak'");
runSql($pdo, "ALTER TABLE produk MODIFY COLUMN status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif'");

// 2. Modifikasi tabel transaksi
runSql($pdo, "ALTER TABLE transaksi ADD COLUMN qty INT(11) NOT NULL DEFAULT 1 AFTER kasir_id");

// 3. Update view v_stok_aktif
runSql($pdo, "
    CREATE OR REPLACE VIEW v_stok_aktif AS
    SELECT
        b.id,
        b.kode_item,
        b.merek,
        kb.nama_kategori AS kategori,
        b.ukuran,
        b.warna,
        b.kondisi,
        b.modal,
        b.harga_jual,
        b.qty,
        (b.harga_jual - b.modal) AS potensi_keuntungan,
        b.tanggal_masuk,
        DATEDIFF(CURDATE(), b.tanggal_masuk) AS hari_di_rak,
        s.nama_supplier
    FROM  produk b
    JOIN  suppliers s ON s.id = b.supplier_id
    JOIN  kategori_produk kb ON kb.id = b.kategori_id
    WHERE b.qty > 0 AND b.status = 'aktif'
");

// 4. Update view v_deadstock
runSql($pdo, "
    CREATE OR REPLACE VIEW v_deadstock AS
    SELECT *
    FROM   v_stok_aktif
    WHERE  hari_di_rak > 30
    ORDER  BY hari_di_rak DESC
");

// 5. Update view v_laporan_transaksi
runSql($pdo, "
    CREATE OR REPLACE VIEW v_laporan_transaksi AS
    SELECT
        t.id AS transaksi_id,
        t.tanggal_jual,
        t.created_at,
        b.kode_item,
        b.merek,
        kb.nama_kategori AS kategori,
        b.ukuran,
        b.kondisi,
        t.qty,
        t.harga_jual,
        t.modal,
        t.keuntungan,
        t.metode_bayar,
        t.catatan,
        u.id AS kasir_id,
        u.nama AS nama_kasir,
        s.nama_supplier
    FROM  transaksi t
    JOIN  produk b ON b.id = t.produk_id
    JOIN  kategori_produk kb ON kb.id = b.kategori_id
    JOIN  users u ON u.id = t.kasir_id
    JOIN  suppliers s ON s.id = b.supplier_id
");

// 6. Update view v_ringkasan_harian
runSql($pdo, "
    CREATE OR REPLACE VIEW v_ringkasan_harian AS
    SELECT
        tanggal_jual,
        SUM(qty) AS jumlah_item_terjual,
        SUM(harga_jual * qty) AS total_pendapatan,
        SUM(modal * qty) AS total_modal,
        SUM(keuntungan * qty) AS total_keuntungan
    FROM  transaksi
    GROUP BY tanggal_jual
    ORDER BY tanggal_jual DESC
");
