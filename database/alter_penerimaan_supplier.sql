ALTER TABLE `penerimaan_barang`
    ADD COLUMN `supplier_id` INT(11) UNSIGNED NULL COMMENT 'FK ke tabel suppliers' AFTER `produk_id`,
    ADD KEY `idx_penerimaan_supplier` (`supplier_id`),
    ADD CONSTRAINT `fk_penerimaan_supplier`
        FOREIGN KEY (`supplier_id`)
        REFERENCES `suppliers` (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
