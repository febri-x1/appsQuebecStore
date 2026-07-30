CREATE OR REPLACE VIEW `v_laporan_transaksi` AS
    SELECT
        t.id                              AS transaksi_id,
        t.tanggal_jual,
        t.created_at,
        b.kode_item,
        b.merek,
        kb.nama_kategori AS kategori,
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
    JOIN  `produk`    b ON b.id = t.produk_id
    JOIN  `kategori_produk` kb ON kb.id = b.kategori_id
    JOIN  `users`     u ON u.id = t.kasir_id
    JOIN  `suppliers` s ON s.id = b.supplier_id;
