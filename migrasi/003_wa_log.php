<?php
// ============================================================
//  MIGRASI 003 — Tabel wa_log (log pengiriman WA bukti bayar)
// ============================================================

db_query("CREATE TABLE IF NOT EXISTS `wa_log` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pembayaran_id` INT UNSIGNED NOT NULL,
  `no_hp`         VARCHAR(20) NOT NULL,
  `status`        ENUM('terkirim','gagal') NOT NULL DEFAULT 'gagal',
  `keterangan`    VARCHAR(255) DEFAULT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pembayaran` (`pembayaran_id`),
  CONSTRAINT `fk_wa_log_pembayaran`
    FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
