<?php
// ============================================================
//  Migrasi 011 — Tabel usage_pppoe (rekam byte-out & uptime)
// ============================================================

db_query("
    CREATE TABLE IF NOT EXISTS usage_pppoe (
        id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pelanggan_id          INT UNSIGNED NOT NULL,
        bulan_tagihan         VARCHAR(7)   NOT NULL,
        bytes_out             BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_bytes_snapshot   BIGINT UNSIGNED NOT NULL DEFAULT 0,
        uptime_seconds        BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_uptime_snapshot  BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_poll_at          DATETIME DEFAULT NULL,
        UNIQUE KEY uk_pelanggan_bulan (pelanggan_id, bulan_tagihan),
        KEY idx_bulan (bulan_tagihan)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
