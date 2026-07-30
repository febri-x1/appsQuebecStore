ALTER TABLE `produk` 
CHANGE `foto` `foto_produk` VARCHAR(255) NULL DEFAULT NULL,
ADD `bahan` VARCHAR(100) NULL AFTER `warna`,
ADD `sumber_barang` VARCHAR(100) NULL AFTER `status`,
ADD `keterangan_sumber` TEXT NULL AFTER `sumber_barang`;

CREATE TABLE IF NOT EXISTS `riwayat_harga` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `produk_id` INT(11) UNSIGNED NOT NULL,
    `harga_lama` DECIMAL(10,2) NOT NULL,
    `harga_baru` DECIMAL(10,2) NOT NULL,
    `diubah_oleh` INT(11) UNSIGNED NOT NULL,
    `tanggal_ubah` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `keterangan` TEXT,
    FOREIGN KEY (`produk_id`) REFERENCES `produk`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`diubah_oleh`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
