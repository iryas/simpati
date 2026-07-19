<?php
// ============================================================
//  Migrasi 016 — Tabel pengumuman
//  Info & pengumuman yang dibuat admin, ditampilkan ke pelanggan
//  lewat portal (beranda + halaman Info & Pengumuman).
// ============================================================

db_query("
    CREATE TABLE IF NOT EXISTS pengumuman (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        judul       VARCHAR(150) NOT NULL,
        isi         TEXT NOT NULL,
        tipe        ENUM('info','penting','promo','maintenance')
                      NOT NULL DEFAULT 'info',
        pinned      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sematkan di atas',
        aktif       TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Tampil di portal',
        dibuat_oleh INT UNSIGNED DEFAULT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_aktif (aktif),
        KEY idx_pinned (pinned),
        CONSTRAINT fk_pengumuman_pengguna FOREIGN KEY (dibuat_oleh)
            REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
