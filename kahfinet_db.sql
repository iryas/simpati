-- ============================================================
--  KAHFINET — Database Schema
--  MySQL / MariaDB
--  Jalankan file ini sekali untuk setup database
-- ============================================================

CREATE DATABASE IF NOT EXISTS `kahfinet_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `kahfinet_db`;

-- ── Tabel Pengguna (User Sistem) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `pengguna` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`        VARCHAR(100) NOT NULL,
  `username`    VARCHAR(50)  NOT NULL UNIQUE,
  `password`    VARCHAR(255) NOT NULL,
  `role`        ENUM('admin','teknisi','kasir') NOT NULL DEFAULT 'kasir',
  `status`      ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `last_login`  DATETIME     DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Remember Tokens ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pengguna_id`   INT UNSIGNED NOT NULL,
  `token`         VARCHAR(128) NOT NULL UNIQUE,
  `expired_at`    DATETIME     NOT NULL,
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Area (lokasi/gang pelanggan) ────────────────────────
CREATE TABLE IF NOT EXISTS `area` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`        VARCHAR(100) NOT NULL,
  `keterangan`  VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Router Mikrotik ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `mikrotik_routers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`        VARCHAR(100) NOT NULL DEFAULT 'Router Utama',
  `host`        VARCHAR(100) NOT NULL,
  `api_port`    SMALLINT UNSIGNED NOT NULL DEFAULT 8728,
  `use_ssl`     TINYINT(1)  NOT NULL DEFAULT 0,
  `username`    VARCHAR(100) NOT NULL,
  `password`    TEXT         NOT NULL COMMENT 'Dienkripsi pakai encrypt_pppoe()',
  `is_active`   TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at`  DATETIME    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME    DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Cache Mirror: PPP Profile & PPP Secret (Mikrotik → app) ──
-- Read-only, ditarik manual lewat tombol "Sync dari Mikrotik" di menu Mikrotik > Profile/Secret.
-- `paket`/`pelanggan` adalah data detail/bisnis yang menempel (link) ke satu baris di sini, 1:1.
CREATE TABLE IF NOT EXISTS `mikrotik_profiles_cache` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mikrotik_router_id`  INT UNSIGNED DEFAULT NULL,
  `ros_id`              VARCHAR(20)  NOT NULL COMMENT 'Nilai .id dari RouterOS',
  `name`                VARCHAR(100) NOT NULL,
  `rate_limit`          VARCHAR(50)  DEFAULT NULL,
  `raw`                 TEXT         DEFAULT NULL COMMENT 'JSON dump lengkap baris dari RouterOS',
  `synced_at`           DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ros_id` (`ros_id`),
  FOREIGN KEY (`mikrotik_router_id`) REFERENCES `mikrotik_routers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Cache Mirror: Device ONU/ONT (ACS → app) ────────────
-- Read-only, ditarik manual lewat tombol "Sync dari ACS" di menu ACS > Device ONU.
-- `pppoe_username` cuma dipakai untuk SARAN mapping (bukan auto-link) — link
-- sesungguhnya ke Secret PPP tetap dipilih manual oleh admin di halaman Device ONU.
CREATE TABLE IF NOT EXISTS `genieacs_devices_cache` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id`       VARCHAR(100) NOT NULL COMMENT 'Nilai _id dari GenieACS',
  `tag`             VARCHAR(100) DEFAULT NULL,
  `manufacturer`    VARCHAR(100) DEFAULT NULL,
  `product_class`   VARCHAR(100) DEFAULT NULL,
  `pppoe_username`  VARCHAR(100) DEFAULT NULL COMMENT 'Dari VirtualParameters.pppoeUsername, bantu suggest mapping',
  `last_inform`     DATETIME     DEFAULT NULL,
  `raw`             MEDIUMTEXT   DEFAULT NULL COMMENT 'JSON dump dari GenieACS (bisa >100KB per device, jangan TEXT)',
  `synced_at`       DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mikrotik_secrets_cache` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mikrotik_router_id`  INT UNSIGNED DEFAULT NULL,
  `genieacs_device_id`  INT UNSIGNED DEFAULT NULL COMMENT 'Mapping manual ke Device ONU, diisi lewat ACS > Device ONU',
  `ros_id`              VARCHAR(20)  NOT NULL COMMENT 'Nilai .id dari RouterOS',
  `name`                VARCHAR(100) NOT NULL COMMENT 'Username PPPoE',
  `service`             VARCHAR(20)  DEFAULT NULL,
  `profile`             VARCHAR(100) DEFAULT NULL,
  `remote_address`      VARCHAR(50)  DEFAULT NULL,
  `disabled`            TINYINT(1)   NOT NULL DEFAULT 0,
  `comment`             VARCHAR(255) DEFAULT NULL,
  `raw`                 TEXT         DEFAULT NULL COMMENT 'JSON dump lengkap baris dari RouterOS',
  `synced_at`           DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ros_id` (`ros_id`),
  FOREIGN KEY (`mikrotik_router_id`) REFERENCES `mikrotik_routers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`genieacs_device_id`) REFERENCES `genieacs_devices_cache`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Paket Internet ──────────────────────────────────────
-- Paket = data bisnis (harga, keterangan) yang menempel ke 1 PPP Profile yang sudah disync dari Mikrotik.
CREATE TABLE IF NOT EXISTS `paket` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mikrotik_profiles_id`  INT UNSIGNED DEFAULT NULL,
  `nama`                  VARCHAR(100) NOT NULL,
  `kecepatan`             INT UNSIGNED NOT NULL COMMENT 'Dalam Mbps',
  `harga`                 DECIMAL(12,0) NOT NULL DEFAULT 0,
  `status`                ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `keterangan`            TEXT         DEFAULT NULL,
  `created_at`            DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`mikrotik_profiles_id`) REFERENCES `mikrotik_profiles_cache`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Pelanggan ──────────────────────────────────────────
-- Pelanggan = data bisnis (nama, alamat, dst) yang menempel ke 1 PPP Secret yang sudah disync dari Mikrotik.
CREATE TABLE IF NOT EXISTS `pelanggan` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`                 VARCHAR(150) NOT NULL,
  `no_hp`                VARCHAR(20)  DEFAULT NULL,
  `alamat`               TEXT         DEFAULT NULL,
  `area_id`              INT UNSIGNED DEFAULT NULL,
  `paket_id`             INT UNSIGNED DEFAULT NULL,
  `mikrotik_secrets_id`  INT UNSIGNED DEFAULT NULL,
  `tgl_daftar`           DATE         DEFAULT NULL,
  `status`               ENUM('aktif','nonaktif','isolir') NOT NULL DEFAULT 'aktif',
  `keterangan`           TEXT         DEFAULT NULL,
  `created_at`           DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`area_id`) REFERENCES `area`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`paket_id`) REFERENCES `paket`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`mikrotik_secrets_id`) REFERENCES `mikrotik_secrets_cache`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Log Aktivitas Pelanggan ──────────────────────────────
-- Dicatat tiap kali status ATAU paket pelanggan berubah (lewat
-- halaman Status Pelanggan), untuk jejak audit. Kolom `tipe`
-- membedakan jenis baris ('status'/'paket'); detail perubahan
-- (nilai lama & baru) disimpan fleksibel di `details` (JSON:
-- {"lama":"...","baru":"..."}) untuk semua tipe.
CREATE TABLE IF NOT EXISTS `pelanggan_status_log` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pelanggan_id`  INT UNSIGNED NOT NULL,
  `tipe`          ENUM('status','paket') NOT NULL DEFAULT 'status',
  `keterangan`    VARCHAR(255) DEFAULT NULL,
  `details`       TEXT DEFAULT NULL COMMENT 'JSON: {"lama":"...","baru":"..."}',
  `diubah_oleh`   INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`diubah_oleh`) REFERENCES `pengguna`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Pembayaran ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pembayaran` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `pelanggan_id`    INT UNSIGNED  NOT NULL,
  `paket_id`        INT UNSIGNED  DEFAULT NULL,
  `kasir_id`        INT UNSIGNED  DEFAULT NULL,
  `jumlah`          DECIMAL(12,0) NOT NULL DEFAULT 0,
  `potongan`        INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Potongan dalam hari',
  `terbayar`        DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT 'Nominal yang benar-benar dibayar',
  `bulan_tagihan`   VARCHAR(7)    DEFAULT NULL COMMENT 'Format: YYYY-MM',
  `tgl_jatuh_tempo` DATE          DEFAULT NULL,
  `tgl_bayar`       DATE          DEFAULT NULL,
  `status`          ENUM('lunas','belum') NOT NULL DEFAULT 'belum',
  `keterangan`      VARCHAR(255)  DEFAULT NULL,
  `created_at`      DATETIME      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`paket_id`)     REFERENCES `paket`(`id`)     ON DELETE SET NULL,
  FOREIGN KEY (`kasir_id`)     REFERENCES `pengguna`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Pengeluaran ────────────────────────────────────────
-- Dipisah per bulan (kolom `bulan`) supaya bisa dihitung
-- "keuntungan = omset bulan ini - pengeluaran bulan ini".
-- Kategori 'bandwidth'/'listrik' dibatasi 1 baris per bulan di
-- level aplikasi (act.php), 'lainnya' bebas berkali-kali.
CREATE TABLE IF NOT EXISTS `pengeluaran` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kategori`      ENUM('bandwidth','listrik','lainnya') NOT NULL,
  `keterangan`    VARCHAR(255) DEFAULT NULL,
  `jumlah`        DECIMAL(12,0) NOT NULL DEFAULT 0,
  `bulan`         VARCHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
  `tanggal`       DATE NOT NULL,
  `dicatat_oleh`  INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bulan` (`bulan`),
  FOREIGN KEY (`dicatat_oleh`) REFERENCES `pengguna`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Pengaturan ACS (GenieACS, TR-069) ────────────────────
-- Dipakai untuk reboot ONU/ONT pelanggan dari jarak jauh, lewat
-- NBI REST API GenieACS. Mapping device <-> pelanggan dilakukan
-- via VirtualParameters.pppoeUsername (cocokkan dengan username
-- PPPoE pelanggan), bukan disimpan manual per pelanggan.
CREATE TABLE IF NOT EXISTS `acs_settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`        VARCHAR(100) NOT NULL DEFAULT 'ACS Utama',
  `base_url`    VARCHAR(255) NOT NULL COMMENT 'Contoh: http://192.168.10.107:7557',
  `username`    VARCHAR(100) DEFAULT NULL COMMENT 'Kosong jika NBI tidak pakai auth',
  `password`    TEXT DEFAULT NULL COMMENT 'Dienkripsi pakai encrypt_pppoe()',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  DATA AWAL (Seed)
-- ============================================================

-- Admin default: username=admin | password=admin123
INSERT INTO `pengguna` (`nama`, `username`, `password`, `role`, `status`) VALUES
('Administrator', 'admin',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   'aktif'),
('Budi Teknisi',  'teknisi', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 'aktif'),
('Siti Kasir',    'kasir',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kasir',   'aktif');
-- ⚠️  Password semua akun: password  (ganti setelah login!)

-- Router Mikrotik default (placeholder — isi host/username/password lewat halaman Pengaturan)
INSERT INTO `mikrotik_routers` (`nama`, `host`, `api_port`, `use_ssl`, `username`, `password`, `is_active`) VALUES
('Router Utama', '', 8728, 0, '', '', 1);

-- Paket default
INSERT INTO `paket` (`nama`, `kecepatan`, `harga`, `status`, `keterangan`) VALUES
('Paket Starter',    5,  100000, 'aktif', 'Cocok untuk pengguna ringan'),
('Paket Basic',     10,  150000, 'aktif', 'Browsing & streaming SD'),
('Paket Standard',  20,  200000, 'aktif', 'Streaming HD & WFH'),
('Paket Premium',   50,  350000, 'aktif', 'Gaming & streaming 4K'),
('Paket Business', 100,  600000, 'aktif', 'Untuk bisnis & kantor');

-- ============================================================
--  SELESAI — Akses: http://localhost/kahfinet/login.php
-- ============================================================


-- ============================================================
--  SECURITY PATCH (USANG): Ubah password_pppoe menjadi TEXT
--  Kolom `username_pppoe`/`password_pppoe` sudah dihapus total dari
--  `pelanggan` — kredensial PPPoE sekarang murni dikelola lewat
--  Mikrotik & dibaca dari `mikrotik_secrets_cache`. Patch ini sudah
--  tidak relevan, dibiarkan sebagai catatan riwayat saja.
-- ============================================================
-- ALTER TABLE `pelanggan` MODIFY COLUMN `password_pppoe` TEXT DEFAULT NULL;

-- ============================================================
--  MIGRASI: Integrasi Mikrotik RouterOS
--  Jalankan jika tabel `paket`/`pelanggan` sudah ada sebelumnya
--  (tidak perlu dijalankan untuk instalasi baru dari file ini)
-- ============================================================
-- CREATE TABLE IF NOT EXISTS `mikrotik_routers` (
--   `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
--   `nama`        VARCHAR(100) NOT NULL DEFAULT 'Router Utama',
--   `host`        VARCHAR(100) NOT NULL,
--   `api_port`    SMALLINT UNSIGNED NOT NULL DEFAULT 8728,
--   `use_ssl`     TINYINT(1)  NOT NULL DEFAULT 0,
--   `username`    VARCHAR(100) NOT NULL,
--   `password`    TEXT         NOT NULL,
--   `is_active`   TINYINT(1)  NOT NULL DEFAULT 1,
--   `created_at`  DATETIME    DEFAULT CURRENT_TIMESTAMP,
--   `updated_at`  DATETIME    DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
--   PRIMARY KEY (`id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- INSERT INTO `mikrotik_routers` (`nama`, `host`, `api_port`, `use_ssl`, `username`, `password`, `is_active`)
-- VALUES ('Router Utama', '', 8728, 0, '', '', 1);
--
-- ALTER TABLE `paket`
--   ADD COLUMN `mikrotik_router_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
--   ADD COLUMN `mikrotik_profile_id` VARCHAR(20) DEFAULT NULL,
--   ADD FOREIGN KEY (`mikrotik_router_id`) REFERENCES `mikrotik_routers`(`id`) ON DELETE SET NULL;
--
-- ALTER TABLE `pelanggan`
--   ADD COLUMN `mikrotik_router_id` INT UNSIGNED DEFAULT NULL AFTER `paket_id`,
--   ADD COLUMN `mikrotik_secret_id` VARCHAR(20) DEFAULT NULL,
--   ADD FOREIGN KEY (`mikrotik_router_id`) REFERENCES `mikrotik_routers`(`id`) ON DELETE SET NULL;
--
-- CREATE TABLE IF NOT EXISTS `mikrotik_profiles_cache` (
--   `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
--   `ros_id`      VARCHAR(20)  NOT NULL,
--   `name`        VARCHAR(100) NOT NULL,
--   `rate_limit`  VARCHAR(50)  DEFAULT NULL,
--   `raw`         TEXT         DEFAULT NULL,
--   `synced_at`   DATETIME     DEFAULT NULL,
--   PRIMARY KEY (`id`),
--   UNIQUE KEY `uniq_ros_id` (`ros_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- CREATE TABLE IF NOT EXISTS `mikrotik_secrets_cache` (
--   `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
--   `ros_id`          VARCHAR(20)  NOT NULL,
--   `name`            VARCHAR(100) NOT NULL,
--   `service`         VARCHAR(20)  DEFAULT NULL,
--   `profile`         VARCHAR(100) DEFAULT NULL,
--   `remote_address`  VARCHAR(50)  DEFAULT NULL,
--   `disabled`        TINYINT(1)   NOT NULL DEFAULT 0,
--   `comment`         VARCHAR(255) DEFAULT NULL,
--   `raw`             TEXT         DEFAULT NULL,
--   `synced_at`       DATETIME     DEFAULT NULL,
--   PRIMARY KEY (`id`),
--   UNIQUE KEY `uniq_ros_id` (`ros_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  MIGRASI: Paket/Pelanggan jadi "link" ke cache Profile/Secret
--  (ganti model push app→Mikrotik jadi link Mikrotik→app)
--  Jalankan jika DB sudah pakai skema migrasi di atas (mikrotik_router_id
--  + mikrotik_profile_id/mikrotik_secret_id di paket/pelanggan).
--  Cek dulu nama FK aktual lewat `SHOW CREATE TABLE paket` /
--  `SHOW CREATE TABLE pelanggan` — nama FK di bawah cuma contoh,
--  sesuaikan dengan nama yang sebenarnya ada di DB Anda.
-- ============================================================
-- ALTER TABLE `mikrotik_profiles_cache`
--   ADD COLUMN `mikrotik_router_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
--   ADD FOREIGN KEY (`mikrotik_router_id`) REFERENCES `mikrotik_routers`(`id`) ON DELETE SET NULL;
--
-- ALTER TABLE `mikrotik_secrets_cache`
--   ADD COLUMN `mikrotik_router_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
--   ADD FOREIGN KEY (`mikrotik_router_id`) REFERENCES `mikrotik_routers`(`id`) ON DELETE SET NULL;
--
-- ALTER TABLE `paket`
--   DROP FOREIGN KEY `paket_ibfk_1`,
--   DROP COLUMN `mikrotik_router_id`,
--   DROP COLUMN `mikrotik_profile_id`,
--   ADD COLUMN `mikrotik_profiles_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
--   ADD FOREIGN KEY (`mikrotik_profiles_id`) REFERENCES `mikrotik_profiles_cache`(`id`) ON DELETE SET NULL;
--
-- ALTER TABLE `pelanggan`
--   DROP FOREIGN KEY `pelanggan_ibfk_2`,
--   DROP COLUMN `mikrotik_router_id`,
--   DROP COLUMN `mikrotik_secret_id`,
--   ADD COLUMN `mikrotik_secrets_id` INT UNSIGNED DEFAULT NULL AFTER `paket_id`,
--   ADD FOREIGN KEY (`mikrotik_secrets_id`) REFERENCES `mikrotik_secrets_cache`(`id`) ON DELETE SET NULL;

-- ============================================================
--  MIGRASI: Hapus username_pppoe/password_pppoe dari pelanggan
--  Kredensial PPPoE sudah sepenuhnya dikelola lewat Mikrotik +
--  mikrotik_secrets_cache, jadi 2 kolom ini tidak lagi dipakai.
-- ============================================================
-- ALTER TABLE `pelanggan`
--   DROP COLUMN `username_pppoe`,
--   DROP COLUMN `password_pppoe`;

-- ============================================================
--  MIGRASI: Generate tagihan massal & form Bayar dengan potongan
-- ============================================================
-- ALTER TABLE `pembayaran`
--   ADD COLUMN `potongan` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Potongan dalam hari' AFTER `jumlah`,
--   ADD COLUMN `terbayar` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT 'Nominal yang benar-benar dibayar' AFTER `potongan`;

-- ============================================================
--  MIGRASI: Hapus kolom tgl_jatuh_tempo dari pelanggan
--  Tanggal jatuh tempo tagihan sudah dikelola lewat tabel
--  `pembayaran.tgl_jatuh_tempo` per-invoice, jadi kolom ini di
--  `pelanggan` tidak lagi dipakai.
-- ============================================================
-- ALTER TABLE `pelanggan`
--   DROP COLUMN `tgl_jatuh_tempo`;

-- ============================================================
--  MIGRASI: Tabel Area (lokasi/gang pelanggan) — menu Data Master
-- ============================================================
-- CREATE TABLE IF NOT EXISTS `area` (
--   `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
--   `nama`        VARCHAR(100) NOT NULL,
--   `keterangan`  VARCHAR(255) DEFAULT NULL,
--   `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
--   `updated_at`  DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
--   PRIMARY KEY (`id`),
--   UNIQUE KEY `uniq_nama` (`nama`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  MIGRASI: Tambah area_id di pelanggan (link ke tabel area)
-- ============================================================
-- ALTER TABLE `pelanggan`
--   ADD COLUMN `area_id` INT UNSIGNED DEFAULT NULL AFTER `alamat`,
--   ADD FOREIGN KEY (`area_id`) REFERENCES `area`(`id`) ON DELETE SET NULL;

-- ============================================================
--  MIGRASI: Log perubahan status pelanggan — menu Status Pelanggan
-- ============================================================
-- CREATE TABLE IF NOT EXISTS `pelanggan_status_log` (
--   `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
--   `pelanggan_id`  INT UNSIGNED NOT NULL,
--   `status_lama`   ENUM('aktif','nonaktif','isolir') DEFAULT NULL,
--   `status_baru`   ENUM('aktif','nonaktif','isolir') NOT NULL,
--   `keterangan`    VARCHAR(255) DEFAULT NULL,
--   `diubah_oleh`   INT UNSIGNED DEFAULT NULL,
--   `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
--   PRIMARY KEY (`id`),
--   FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan`(`id`) ON DELETE CASCADE,
--   FOREIGN KEY (`diubah_oleh`) REFERENCES `pengguna`(`id`) ON DELETE SET NULL
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  MIGRASI: Tambah kolom keterangan di pelanggan_status_log
--  (kalau sudah pernah buat tabel di atas tanpa kolom ini)
-- ============================================================
-- ALTER TABLE `pelanggan_status_log`
--   ADD COLUMN `keterangan` VARCHAR(255) DEFAULT NULL AFTER `status_baru`;

-- ============================================================
--  MIGRASI: Tambah kolom tipe & details di pelanggan_status_log
--  (untuk mencatat juga perubahan paket, bukan cuma status)
-- ============================================================
-- ALTER TABLE `pelanggan_status_log`
--   ADD COLUMN `tipe` ENUM('status','paket') NOT NULL DEFAULT 'status' AFTER `pelanggan_id`,
--   MODIFY `status_baru` ENUM('aktif','nonaktif','isolir') NULL DEFAULT NULL,
--   ADD COLUMN `details` TEXT DEFAULT NULL AFTER `keterangan`;

-- ============================================================
--  MIGRASI: Hapus kolom status_lama & status_baru di
--  pelanggan_status_log (digantikan kolom `details` JSON yang
--  fleksibel untuk semua tipe, termasuk 'status'). Jalankan
--  migrasi tipe/details di atas dulu, lalu pindahkan data lama
--  ke `details` sebelum drop kolom, contoh:
--    UPDATE pelanggan_status_log
--      SET details = JSON_OBJECT('lama', status_lama, 'baru', status_baru)
--      WHERE tipe = 'status' AND details IS NULL;
-- ============================================================
-- ALTER TABLE `pelanggan_status_log`
--   DROP COLUMN `status_lama`,
--   DROP COLUMN `status_baru`;

-- ============================================================
--  MIGRASI: Tambah tabel genieacs_devices_cache + kolom
--  genieacs_device_id di mikrotik_secrets_cache (mapping manual
--  Secret PPP <-> Device ONU/ONT, untuk fitur reboot dari ACS)
-- ============================================================
-- CREATE TABLE IF NOT EXISTS `genieacs_devices_cache` (
--   `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
--   `device_id`       VARCHAR(100) NOT NULL COMMENT 'Nilai _id dari GenieACS',
--   `tag`             VARCHAR(100) DEFAULT NULL,
--   `manufacturer`    VARCHAR(100) DEFAULT NULL,
--   `product_class`   VARCHAR(100) DEFAULT NULL,
--   `pppoe_username`  VARCHAR(100) DEFAULT NULL,
--   `last_inform`     DATETIME     DEFAULT NULL,
--   `raw`             MEDIUMTEXT   DEFAULT NULL COMMENT 'Bisa >100KB per device, jangan TEXT',
--   `synced_at`       DATETIME     DEFAULT NULL,
--   PRIMARY KEY (`id`),
--   UNIQUE KEY `uniq_device_id` (`device_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- ALTER TABLE `mikrotik_secrets_cache`
--   ADD COLUMN `genieacs_device_id` INT UNSIGNED DEFAULT NULL AFTER `mikrotik_router_id`,
--   ADD FOREIGN KEY (`genieacs_device_id`) REFERENCES `genieacs_devices_cache`(`id`) ON DELETE SET NULL;

