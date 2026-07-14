<?php
// ============================================================
//  MIGRASI 010 — Tabel antrian pengiriman WA (wa_queue)
// ============================================================

db_query("CREATE TABLE IF NOT EXISTS wa_queue (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pembayaran_id  INT UNSIGNED NULL,
    no_hp          VARCHAR(20)  NOT NULL,
    pesan          TEXT         NOT NULL,
    tipe           ENUM('bukti_bayar','isolir') NOT NULL DEFAULT 'bukti_bayar',
    status         ENUM('pending','processing','terkirim','gagal') NOT NULL DEFAULT 'pending',
    attempts       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt   DATETIME NULL,
    next_retry     DATETIME NULL,
    error_msg      VARCHAR(500) NULL,
    created_at     DATETIME NOT NULL,
    sent_at        DATETIME NULL,
    INDEX idx_status_retry   (status, next_retry),
    INDEX idx_pembayaran_id  (pembayaran_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
