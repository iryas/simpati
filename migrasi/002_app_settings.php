<?php
// ============================================================
//  MIGRASI 002 — Tabel app_settings (key-value pengaturan app)
// ============================================================

db_query("CREATE TABLE IF NOT EXISTS `app_settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_val` TEXT DEFAULT NULL,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed nilai default
$defaults = [
    'tgl_mulai_tagihan' => '1',
    'nama_isp'          => 'KAHFINET',
];

foreach ($defaults as $key => $val) {
    db_query(
        "INSERT IGNORE INTO app_settings (setting_key, setting_val) VALUES (?, ?)",
        [$key, $val]
    );
}
