CREATE TABLE IF NOT EXISTS `penerimaan_barang` (
    `id_penerimaan`  INT(11)      UNSIGNED NOT NULL AUTO_INCREMENT,
    `no_penerimaan`  VARCHAR(30)  NOT NULL COMMENT 'Nomor penerimaan unik, contoh: PNR-20260717-0001',
    `produk_id`      INT(11)      UNSIGNED NOT NULL COMMENT 'FK ke tabel produk',
    `qty`            INT(11)      UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Jumlah item diterima',
    `keterangan`     TEXT         DEFAULT NULL COMMENT 'Catatan penerimaan barang',
    `admin_id`       INT(11)      UNSIGNED NOT NULL COMMENT 'FK ke tabel users (admin yang menerima)',
    `tanggal_terima` DATE         NOT NULL COMMENT 'Tanggal penerimaan barang',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id_penerimaan`),
    UNIQUE KEY `uq_no_penerimaan` (`no_penerimaan`),
    KEY `idx_penerimaan_produk`  (`produk_id`),
    KEY `idx_penerimaan_admin`   (`admin_id`),
    KEY `idx_penerimaan_tanggal` (`tanggal_terima`),

    CONSTRAINT `fk_penerimaan_produk`
        FOREIGN KEY (`produk_id`)
        REFERENCES `produk` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT `fk_penerimaan_admin`
        FOREIGN KEY (`admin_id`)
        REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Riwayat penerimaan / restok barang oleh admin';
