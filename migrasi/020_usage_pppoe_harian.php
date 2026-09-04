<?php
// ============================================================
//  Migrasi 020 — Tabel usage_pppoe_harian (rekam byte-out &
//  uptime PER TANGGAL, buat modal "Detail Pemakaian Harian").
//  usage_pppoe cuma nyimpen total kumulatif per bulan_tagihan —
//  tabel ini mulai mencatat sejak migrasi ini jalan, nggak bisa
//  direkonstruksi mundur.
// ============================================================

db_query("
    CREATE TABLE IF NOT EXISTS usage_pppoe_harian (
        id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pelanggan_id          INT UNSIGNED NOT NULL,
        tanggal               DATE NOT NULL,
        bytes_out             BIGINT UNSIGNED NOT NULL DEFAULT 0,
        uptime_seconds        BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_poll_at          DATETIME DEFAULT NULL,
        UNIQUE KEY uk_pelanggan_tanggal (pelanggan_id, tanggal),
        KEY idx_tanggal (tanggal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
