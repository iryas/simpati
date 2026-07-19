<?php
// ============================================================
//  Migrasi 015 — Tabel tiket_gangguan
//  Laporan gangguan yang dibuat pelanggan lewat portal,
//  ditangani oleh admin/teknisi dari sisi aplikasi.
// ============================================================

db_query("
    CREATE TABLE IF NOT EXISTS tiket_gangguan (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pelanggan_id    INT UNSIGNED NOT NULL,
        kategori        ENUM('koneksi_lambat','tidak_konek','perangkat','tagihan','lainnya')
                          NOT NULL DEFAULT 'lainnya',
        deskripsi       TEXT NOT NULL,
        status          ENUM('baru','diproses','selesai','ditutup')
                          NOT NULL DEFAULT 'baru',
        balasan         TEXT DEFAULT NULL COMMENT 'Tanggapan admin/teknisi',
        ditangani_oleh  INT UNSIGNED DEFAULT NULL,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_pelanggan (pelanggan_id),
        KEY idx_status (status),
        CONSTRAINT fk_tiket_pelanggan FOREIGN KEY (pelanggan_id)
            REFERENCES pelanggan(id) ON DELETE CASCADE,
        CONSTRAINT fk_tiket_petugas FOREIGN KEY (ditangani_oleh)
            REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
