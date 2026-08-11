<?php
// ============================================================
//  Migrasi 018 — Tabel wifi_change_log
//  Audit trail perubahan Nama/Password WiFi via Monitoring
//  (GenieACS TR-069). Password TIDAK disimpan — hanya dicatat
//  apakah password diubah, oleh siapa, ke ONU mana, dan hasilnya.
// ============================================================

db_query("
    CREATE TABLE IF NOT EXISTS wifi_change_log (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        device_id    VARCHAR(191) NOT NULL,
        pppoe        VARCHAR(191) DEFAULT NULL,
        pelanggan    VARCHAR(191) DEFAULT NULL,
        teknisi_id   INT UNSIGNED DEFAULT NULL,
        teknisi_nama VARCHAR(191) DEFAULT NULL,
        ssid_lama    VARCHAR(191) DEFAULT NULL,
        ssid_baru    VARCHAR(191) DEFAULT NULL,
        ubah_ssid    TINYINT(1) NOT NULL DEFAULT 0,
        ubah_pass    TINYINT(1) NOT NULL DEFAULT 0,
        status       VARCHAR(20)  NOT NULL DEFAULT 'queued',
        pesan        VARCHAR(255) DEFAULT NULL,
        ip_address   VARCHAR(45)  DEFAULT NULL,
        created_at   DATETIME     DEFAULT NULL,
        KEY idx_device (device_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
