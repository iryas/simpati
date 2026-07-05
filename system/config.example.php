<?php
// ============================================================
//  KAHFINET - Konfigurasi Aplikasi (TEMPLATE)
//  Salin file ini menjadi config.php lalu sesuaikan nilainya.
//  JANGAN commit config.php ke git — file ini sudah di .gitignore
// ============================================================

define('APP_NAME',    'SIMPATI');
define('APP_VERSION', '1.3.0');  // Jangan diubah manual — dikelola oleh developer
define('BASE_URL',    'http://localhost/kahfinet1/');  // Sesuaikan dengan URL server

// ── Mode Produksi ─────────────────────────────────────────────
// Ganti ke true saat deploy ke server production
define('APP_DEBUG', false);

if (!APP_DEBUG) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'kahfinet_db');   // Nama database
define('DB_USER', 'root');          // Username database
define('DB_PASS', '');              // Password database
define('DB_CHAR', 'utf8mb4');

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME',     'kahfinet_sess');
define('REMEMBER_ME_DAYS', 30);
define('COOKIE_NAME',      'kahfinet_remember');

// ── Brute Force Protection ────────────────────────────────────
define('LOGIN_MAX_ATTEMPTS',   5);   // Maks percobaan login gagal
define('LOGIN_LOCKOUT_MINUTES', 15); // Durasi lockout (menit)

// ── Enkripsi PPPoE ────────────────────────────────────────────
// Ganti dengan string acak 32 karakter yang unik per instalasi!
// Generate: php -r "echo bin2hex(random_bytes(16));"
define('PPPOE_ENCRYPT_KEY', 'GANTI_DENGAN_32_KARAKTER_ACAK___');

// ── Role ─────────────────────────────────────────────────────
define('ROLE_ADMIN',    'admin');
define('ROLE_TEKNISI',  'teknisi');
define('ROLE_KASIR',    'kasir');

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Jayapura'); // Sesuaikan timezone

// ── Buat folder logs jika belum ada ──────────────────────────
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
