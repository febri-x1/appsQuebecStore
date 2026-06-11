-- ============================================================
--  QUEBEC STORE — Seeder Database
--  File    : database/seeder.sql
--  Deskripsi: Mengisi data awal untuk keperluan testing aplikasi
-- ============================================================
--  Cara penggunaan:
--  Jalankan file ini SETELAH menjalankan schema.sql
--  CLI: mysql -u root -p quebec_store_db < database/seeder.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `transaksi`;
ALTER TABLE `transaksi` AUTO_INCREMENT = 1;
DELETE FROM `barang`;
ALTER TABLE `barang` AUTO_INCREMENT = 1;
DELETE FROM `suppliers`;
ALTER TABLE `suppliers` AUTO_INCREMENT = 1;
DELETE FROM `users`;
ALTER TABLE `users` AUTO_INCREMENT = 1;

-- ------------------------------------------------------------
-- 1. Data Users (Password untuk semua akun adalah: admin123)
-- ------------------------------------------------------------
INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `aktif`) VALUES
(1, 'Budi (Pemilik)', 'admin@quebec.com', '$2y$10$Zcd7D2Kc.c/pbOkNxAq4ce92kTkgFC7QXo4Kc8.bIpXqyU6otLJli', 'pemilik', 1),
(2, 'Siti (Kasir)', 'kasir@quebec.com', '$2y$10$Zcd7D2Kc.c/pbOkNxAq4ce92kTkgFC7QXo4Kc8.bIpXqyU6otLJli', 'kasir', 1);

-- ------------------------------------------------------------
-- 2. Data Suppliers
-- ------------------------------------------------------------
INSERT INTO `suppliers` (`id`, `nama_supplier`, `telepon`, `alamat`, `harga_per_bal`, `isi_per_bal`) VALUES
(1, 'Thrift Gombong', '081234567890', 'Gombong, Kebumen', 3500000.00, 150),
(2, 'Jakarta Ball Import', '089876543210', 'Pasar Senen, Jakarta', 5000000.00, 120);

-- ------------------------------------------------------------
-- 3. Data Barang 
-- Catatan: Menggunakan fungsi dinamis untuk memicu fitur Deadstock
-- ------------------------------------------------------------
INSERT INTO `barang` (`id`, `kode_item`, `supplier_id`, `merek`, `kategori`, `ukuran`, `warna`, `kondisi`, `deskripsi`, `modal`, `harga_jual`, `status`, `tanggal_masuk`) VALUES
-- Deadstock items (> 30 hari)
(1, 'QS-10001', 1, 'Uniqlo', 'kaos', 'M', 'Putih', 'A', 'Mulus banget', 23333.33, 75000.00, 'di_rak', DATE_SUB(CURDATE(), INTERVAL 45 DAY)),
(2, 'QS-10002', 1, 'Dickies', 'kemeja', 'L', 'Hitam', 'A', '-', 23333.33, 120000.00, 'di_rak', DATE_SUB(CURDATE(), INTERVAL 35 DAY)),
-- Item terjual
(3, 'QS-10003', 2, 'Levis', 'celana_panjang', '32', 'Biru', 'B', 'Pudar di lutut', 41666.67, 150000.00, 'terjual', DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(4, 'QS-10004', 2, 'Nike', 'jaket', 'XL', 'Hitam', 'A', 'Windbreaker mulus', 41666.67, 200000.00, 'terjual', DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
(6, 'QS-10006', 2, 'Adidas', 'celana_pendek', 'M', 'Hitam', 'A', '-', 41666.67, 85000.00, 'terjual', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
-- Item baru di rak
(5, 'QS-10005', 1, 'H&M', 'hoodie', 'L', 'Abu-abu', 'B', 'Sablon sedikit retak', 23333.33, 135000.00, 'di_rak', DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(7, 'QS-10007', 1, 'Zara', 'kemeja', 'S', 'Putih', 'C', 'Ada noda kecil di kerah', 23333.33, 90000.00, 'di_rak', CURDATE());

-- ------------------------------------------------------------
-- 4. Data Transaksi
-- Mengacu pada barang yang berstatus 'terjual' di atas
-- ------------------------------------------------------------
INSERT INTO `transaksi` (`id`, `barang_id`, `kasir_id`, `harga_jual`, `modal`, `metode_bayar`, `catatan`, `tanggal_jual`, `created_at`) VALUES
(1, 3, 2, 150000.00, 41666.67, 'tunai', 'Pelanggan tawar 150k', DATE_SUB(CURDATE(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, 4, 2, 200000.00, 41666.67, 'qris', '-', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
-- Transaksi hari ini
(3, 6, 1, 85000.00, 41666.67, 'transfer', '-', CURDATE(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- Selesai.
-- Password untuk login: admin123