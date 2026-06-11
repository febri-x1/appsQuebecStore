-- ============================================================
--  QUEBEC STORE — Sistem Informasi Monitoring Aliran Barang
--  File    : database/schema.sql
--  Deskripsi: Script DDL lengkap untuk membuat semua tabel
--  Database : quebec_store_db
--  Versi   : 1.0
--  Dibuat  : 2025
-- ============================================================
--  Cara penggunaan:
--  1. Buka phpMyAdmin atau MySQL CLI
--  2. Buat database: CREATE DATABASE quebec_store_db;
--  3. Pilih database tersebut, lalu jalankan file ini
--     CLI: mysql -u root -p quebec_store_db < database/schema.sql
-- ============================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+07:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Hapus tabel lama jika ada (urutan penting: tabel anak dulu)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `transaksi`;
DROP TABLE IF EXISTS `barang`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;

-- ============================================================
-- TABEL 1: users
-- Menyimpan akun pengguna sistem (pemilik & kasir)
-- ============================================================
CREATE TABLE `users` (
    `id`         INT(11)      UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama`       VARCHAR(100) NOT NULL COMMENT 'Nama lengkap pengguna',
    `email`      VARCHAR(150) NOT NULL COMMENT 'Email untuk login',
    `password`   VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt dari password',
    `role`       ENUM('pemilik','kasir') NOT NULL DEFAULT 'kasir' COMMENT 'Hak akses pengguna',
    `aktif`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role`  (`role`),
    KEY `idx_users_aktif` (`aktif`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Akun pengguna sistem: pemilik toko dan kasir';


-- ============================================================
-- TABEL 2: suppliers
-- Menyimpan data pemasok bal pakaian bekas
-- Modal per item dihitung dari rata-rata harga per bal
-- ============================================================
CREATE TABLE `suppliers` (
    `id`            INT(11)        UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_supplier` VARCHAR(150)   NOT NULL COMMENT 'Nama supplier / pemasok bal',
    `telepon`       VARCHAR(20)    DEFAULT NULL COMMENT 'Nomor telepon supplier',
    `alamat`        TEXT           DEFAULT NULL COMMENT 'Alamat supplier',
    `harga_per_bal` DECIMAL(12,2)  NOT NULL DEFAULT 0.00 COMMENT 'Harga beli satu bal (Rp)',
    `isi_per_bal`   SMALLINT(5)    UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Estimasi jumlah item per bal',
    `modal_per_item` DECIMAL(10,2) GENERATED ALWAYS AS
                     (ROUND(`harga_per_bal` / `isi_per_bal`, 2)) STORED
                     COMMENT 'Otomatis: harga_per_bal / isi_per_bal (Rp)',
    `keterangan`    TEXT           DEFAULT NULL COMMENT 'Catatan tambahan',
    `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_suppliers_nama` (`nama_supplier`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Data pemasok bal pakaian bekas beserta kalkulasi modal per item';


-- ============================================================
-- TABEL 3: barang
-- Tabel inti sistem — setiap baris = 1 item fisik unik
-- (1 SKU = 1 barang, tidak ada duplikat stok)
-- ============================================================
CREATE TABLE `barang` (
    `id`          INT(11)        UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_item`   VARCHAR(20)    NOT NULL COMMENT 'Kode unik item, contoh: QS-2025-00001',
    `supplier_id` INT(11)        UNSIGNED NOT NULL COMMENT 'FK ke tabel suppliers',
    `merek`       VARCHAR(100)   NOT NULL COMMENT 'Merek pakaian, contoh: Levis, Zara, H&M',
    `kategori`    ENUM(
                    'kaos',
                    'kemeja',
                    'celana_panjang',
                    'celana_pendek',
                    'jaket',
                    'hoodie',
                    'dress',
                    'rok',
                    'outer',
                    'aksesoris',
                    'lainnya'
                  ) NOT NULL DEFAULT 'lainnya' COMMENT 'Kategori jenis pakaian',
    `ukuran`      VARCHAR(10)    NOT NULL COMMENT 'Ukuran: XS, S, M, L, XL, XXL, atau angka',
    `warna`       VARCHAR(50)    NOT NULL COMMENT 'Warna dominan item',
    `kondisi`     ENUM('A','B','C') NOT NULL COMMENT 'A=Sangat Baik, B=Baik, C=Cukup',
    `deskripsi`   TEXT           DEFAULT NULL COMMENT 'Catatan kondisi detail, cacat, dsb.',
    `foto`        VARCHAR(255)   DEFAULT NULL COMMENT 'Path foto item di folder assets/uploads/',
    `modal`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Modal per item (Rp) dari harga bal',
    `harga_jual`  DECIMAL(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Harga jual yang ditetapkan (Rp)',
    `status`      ENUM('di_rak','terjual','rusak') NOT NULL DEFAULT 'di_rak'
                  COMMENT 'Status item: di_rak=tersedia, terjual=sudah laku, rusak=tidak dijual',
    `tanggal_masuk` DATE         NOT NULL COMMENT 'Tanggal barang masuk / didaftarkan ke sistem',
    `created_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_barang_kode_item` (`kode_item`),
    KEY `idx_barang_supplier`      (`supplier_id`),
    KEY `idx_barang_status`        (`status`),
    KEY `idx_barang_kategori`      (`kategori`),
    KEY `idx_barang_kondisi`       (`kondisi`),
    KEY `idx_barang_tanggal_masuk` (`tanggal_masuk`),
    KEY `idx_barang_merek`         (`merek`),

    CONSTRAINT `fk_barang_supplier`
        FOREIGN KEY (`supplier_id`)
        REFERENCES `suppliers` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Item pakaian thrifting — 1 baris = 1 item fisik unik';


-- ============================================================
-- TABEL 4: transaksi
-- Mencatat setiap penjualan yang terjadi di toko
-- Keuntungan = harga_jual - modal (disimpan saat transaksi)
-- ============================================================
CREATE TABLE `transaksi` (
    `id`           INT(11)       UNSIGNED NOT NULL AUTO_INCREMENT,
    `barang_id`    INT(11)       UNSIGNED NOT NULL COMMENT 'FK ke tabel barang',
    `kasir_id`     INT(11)       UNSIGNED NOT NULL COMMENT 'FK ke tabel users (kasir yang melayani)',
    `harga_jual`   DECIMAL(10,2) NOT NULL COMMENT 'Harga terjual saat transaksi (Rp)',
    `modal`        DECIMAL(10,2) NOT NULL COMMENT 'Modal item saat transaksi (snapshot)',
    `keuntungan`   DECIMAL(10,2) GENERATED ALWAYS AS
                   (`harga_jual` - `modal`) STORED
                   COMMENT 'Otomatis: harga_jual - modal (Rp)',
    `metode_bayar` ENUM('tunai','qris','transfer') NOT NULL DEFAULT 'tunai'
                   COMMENT 'Metode pembayaran yang digunakan',
    `catatan`      VARCHAR(255)  DEFAULT NULL COMMENT 'Catatan transaksi opsional',
    `tanggal_jual` DATE          NOT NULL COMMENT 'Tanggal transaksi penjualan',
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_transaksi_barang`       (`barang_id`),
    KEY `idx_transaksi_kasir`        (`kasir_id`),
    KEY `idx_transaksi_tanggal_jual` (`tanggal_jual`),
    KEY `idx_transaksi_metode_bayar` (`metode_bayar`),

    CONSTRAINT `fk_transaksi_barang`
        FOREIGN KEY (`barang_id`)
        REFERENCES `barang` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT `fk_transaksi_kasir`
        FOREIGN KEY (`kasir_id`)
        REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Riwayat transaksi penjualan — keuntungan dihitung otomatis';


-- ============================================================
-- VIEW 1: v_stok_aktif
-- Barang yang masih tersedia di rak (belum terjual)
-- ============================================================
CREATE OR REPLACE VIEW `v_stok_aktif` AS
    SELECT
        b.id,
        b.kode_item,
        b.merek,
        b.kategori,
        b.ukuran,
        b.warna,
        b.kondisi,
        b.modal,
        b.harga_jual,
        b.harga_jual - b.modal            AS potensi_keuntungan,
        b.tanggal_masuk,
        DATEDIFF(CURDATE(), b.tanggal_masuk) AS hari_di_rak,
        s.nama_supplier
    FROM  `barang`    b
    JOIN  `suppliers` s ON s.id = b.supplier_id
    WHERE b.status = 'di_rak';


-- ============================================================
-- VIEW 2: v_deadstock
-- Barang yang sudah > 30 hari di rak dan belum terjual
-- Digunakan untuk alert deadstock di dashboard
-- ============================================================
CREATE OR REPLACE VIEW `v_deadstock` AS
    SELECT *
    FROM   `v_stok_aktif`
    WHERE  hari_di_rak > 30
    ORDER  BY hari_di_rak DESC;


-- ============================================================
-- VIEW 3: v_laporan_transaksi
-- Gabungan data transaksi dengan detail barang dan kasir
-- Digunakan untuk halaman laporan dan dashboard
-- ============================================================
CREATE OR REPLACE VIEW `v_laporan_transaksi` AS
    SELECT
        t.id                              AS transaksi_id,
        t.tanggal_jual,
        t.created_at,
        b.kode_item,
        b.merek,
        b.kategori,
        b.ukuran,
        b.kondisi,
        t.harga_jual,
        t.modal,
        t.keuntungan,
        t.metode_bayar,
        t.catatan,
        u.id                              AS kasir_id,
        u.nama                            AS nama_kasir,
        s.nama_supplier
    FROM  `transaksi` t
    JOIN  `barang`    b ON b.id = t.barang_id
    JOIN  `users`     u ON u.id = t.kasir_id
    JOIN  `suppliers` s ON s.id = b.supplier_id;


-- ============================================================
-- VIEW 4: v_ringkasan_harian
-- Ringkasan pendapatan per hari — untuk grafik dashboard
-- ============================================================
CREATE OR REPLACE VIEW `v_ringkasan_harian` AS
    SELECT
        tanggal_jual,
        COUNT(*)          AS jumlah_item_terjual,
        SUM(harga_jual)   AS total_pendapatan,
        SUM(modal)        AS total_modal,
        SUM(keuntungan)   AS total_keuntungan
    FROM  `transaksi`
    GROUP BY tanggal_jual
    ORDER BY tanggal_jual DESC;


SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Selesai. Tabel yang dibuat:
--   1. users        — akun pemilik & kasir
--   2. suppliers    — pemasok bal pakaian
--   3. barang       — item pakaian (1 baris = 1 fisik unik)
--   4. transaksi    — riwayat penjualan
--
-- View yang dibuat:
--   1. v_stok_aktif          — barang masih di rak
--   2. v_deadstock           — barang > 30 hari belum terjual
--   3. v_laporan_transaksi   — laporan gabungan untuk pelaporan
--   4. v_ringkasan_harian    — ringkasan pendapatan per hari
--
-- Langkah selanjutnya:
--   Jalankan database/seeder.sql untuk mengisi data awal
-- ============================================================
