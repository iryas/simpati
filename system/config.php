<?php
// ============================================================
//  KAHFINET - Konfigurasi Aplikasi
// ============================================================

define('APP_NAME',    'SIMPATI');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost:8000/kahfinet1/');

// ── Mode Produksi ─────────────────────────────────────────────
// Ganti ke TRUE saat deploy ke server production
define('APP_DEBUG', false);

if (!APP_DEBUG) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    // Log error ke file, bukan ditampilkan ke browser
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'kahfinet_db');
define('DB_USER', 'muhyasir');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME',     'kahfinet_sess');
define('REMEMBER_ME_DAYS', 30);
define('COOKIE_NAME',      'kahfinet_remember');

// ── Brute Force Protection ────────────────────────────────────
define('LOGIN_MAX_ATTEMPTS', 5);       // Maks percobaan login
define('LOGIN_LOCKOUT_MINUTES', 15);   // Durasi lockout (menit)

// ── Enkripsi PPPoE ────────────────────────────────────────────
// Ganti dengan string acak 32 karakter yang kuat!
define('PPPOE_ENCRYPT_KEY', 'd089051154d00fcfe91453b75e4d41d5');

// ── Role ─────────────────────────────────────────────────────
define('ROLE_ADMIN',    'admin');
define('ROLE_TEKNISI',  'teknisi');
define('ROLE_KASIR',    'kasir');

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Jayapura');

// ── Buat folder logs jika belum ada ──────────────────────────
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
