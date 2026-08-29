<?php
// ============================================================
//  Migrasi 019 — Tabel monitor_alert_state
//  Melacak status terakhir tiap "hal yang dipantau" (ONU offline
//  per area/individual, kualitas sinyal per ONU) supaya notifikasi
//  Telegram hanya dikirim saat status BERUBAH, bukan tiap siklus
//  sync (mencegah spam notifikasi untuk masalah yang sama).
// ============================================================

db_query("
    CREATE TABLE IF NOT EXISTS monitor_alert_state (
        alert_key   VARCHAR(191) NOT NULL PRIMARY KEY,
        state       VARCHAR(30)  NOT NULL,
        context     VARCHAR(255) DEFAULT NULL,
        updated_at  DATETIME NOT NULL,
        KEY idx_state (state)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
