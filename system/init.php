<?php
// ============================================================
//  KAHFINET - Inisialisasi Aplikasi (Diperkuat)
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/mikrotik.php';
require_once __DIR__ . '/acs.php';

// Script CLI (cron) tidak punya konteks HTTP/session — skip header & session.
if (PHP_SAPI !== 'cli') {

// ── Security Headers ──────────────────────────────────────────
// Cegah clickjacking, sniffing, XSS dari browser lama
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.datatables.net; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.datatables.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data:;");
// Aktifkan baris berikut jika sudah pakai HTTPS:
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// ── Session Hardening ─────────────────────────────────────────
ini_set('session.cookie_httponly', 1);     // Cookie tidak bisa diakses JS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);     // Tolak session ID yang tidak dikenal
ini_set('session.gc_maxlifetime', 7200);   // Session mati setelah 2 jam idle
// Aktifkan jika sudah HTTPS:
// ini_set('session.cookie_secure', 1);

session_name(SESSION_NAME);
session_start();

// Cegah session fixation: regenerate ID tiap 30 menit
if (!isset($_SESSION['_last_regen'])) {
    $_SESSION['_last_regen'] = time();
} elseif (time() - $_SESSION['_last_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}

// ── Remember Me ──────────────────────────────────────────────
if (!isset($_SESSION['user_id']) && isset($_COOKIE[COOKIE_NAME])) {
    $token = $_COOKIE[COOKIE_NAME];

    // Validasi format token (hex 64 karakter)
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $row = db_row(
            "SELECT u.*, t.token FROM pengguna u
             JOIN remember_tokens t ON t.pengguna_id = u.id
             WHERE t.token = ? AND t.expired_at > NOW() AND u.status = 'aktif'",
            [$token]
        );
        if ($row) {
            // Rotate token (cegah token reuse attack)
            $newToken  = generate_token();
            $newExpire = date('Y-m-d H:i:s', time() + (REMEMBER_ME_DAYS * 86400));
            db_query(
                "UPDATE remember_tokens SET token = ?, expired_at = ? WHERE token = ?",
                [$newToken, $newExpire, $token]
            );
            $cookieSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie(COOKIE_NAME, $newToken, time() + (REMEMBER_ME_DAYS * 86400),
                      '/', '', $cookieSecure, true);

            $_SESSION['user_id']   = $row['id'];
            $_SESSION['user_nama'] = $row['nama'];
            $_SESSION['user_role'] = $row['role'];
            session_regenerate_id(true);
        } else {
            // Token tidak valid / expired — hapus cookie
            setcookie(COOKIE_NAME, '', time() - 3600, '/');
        }
    } else {
        setcookie(COOKIE_NAME, '', time() - 3600, '/');
    }
}

} // end PHP_SAPI !== 'cli'

// ── Koneksi Database (PDO) ────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHAR);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('<div style="padding:20px;font-family:monospace;color:red">
                     <b>Database Error:</b> ' . htmlspecialchars($e->getMessage()) . '</div>');
            } else {
                die('<div style="padding:20px;font-family:sans-serif;color:#333">
                     <b>Sistem sedang mengalami gangguan. Silakan hubungi administrator.</b></div>');
            }
        }
    }
    return $pdo;
}

// ── Helper Query ─────────────────────────────────────────────
function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_rows(string $sql, array $params = []): array {
    return db_query($sql, $params)->fetchAll();
}

function db_row(string $sql, array $params = []): array|false {
    return db_query($sql, $params)->fetch();
}

function db_insert(string $table, array $data): string {
    $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
    $vals = implode(',', array_fill(0, count($data), '?'));
    db_query("INSERT INTO `$table` ($cols) VALUES ($vals)", array_values($data));
    return db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int {
    $set  = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
    $stmt = db_query("UPDATE `$table` SET $set WHERE $where",
                     array_merge(array_values($data), $whereParams));
    return $stmt->rowCount();
}

function db_delete(string $table, string $where, array $params = []): int {
    return db_query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
}

// ── Auth Guard ────────────────────────────────────────────────
function auth_check(): void {
    if (!isset($_SESSION['user_id'])) {
        redirect(BASE_URL . 'login.php');
    }
}

function auth_role(array $roles): void {
    auth_check();
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk halaman ini.');
        redirect(BASE_URL . 'index.php');
    }
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_user(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'nama' => $_SESSION['user_nama'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
    ];
}

// ── Brute Force: Cek Lockout ──────────────────────────────────
function login_is_locked(string $identifier): bool {
    $key    = 'login_attempts_' . md5($identifier);
    $locked = 'login_locked_until_' . md5($identifier);

    if (isset($_SESSION[$locked]) && time() < $_SESSION[$locked]) {
        return true;
    }
    // Reset jika lockout sudah berakhir
    if (isset($_SESSION[$locked]) && time() >= $_SESSION[$locked]) {
        unset($_SESSION[$key], $_SESSION[$locked]);
    }
    return false;
}

function login_record_fail(string $identifier): void {
    $key    = 'login_attempts_' . md5($identifier);
    $locked = 'login_locked_until_' . md5($identifier);

    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;

    if ($_SESSION[$key] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION[$locked] = time() + (LOGIN_LOCKOUT_MINUTES * 60);
        unset($_SESSION[$key]);
    }
}

function login_reset(string $identifier): void {
    $key    = 'login_attempts_' . md5($identifier);
    $locked = 'login_locked_until_' . md5($identifier);
    unset($_SESSION[$key], $_SESSION[$locked]);
}

function login_lockout_remaining(string $identifier): int {
    $locked = 'login_locked_until_' . md5($identifier);
    if (isset($_SESSION[$locked])) {
        return max(0, (int)(($_SESSION[$locked] - time()) / 60));
    }
    return 0;
}

// ── Enkripsi PPPoE (AES-256-CBC) ─────────────────────────────
function encrypt_pppoe(string $plaintext): string {
    if (empty($plaintext)) return '';
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($plaintext, 'AES-256-CBC',
                           hash('sha256', PPPOE_ENCRYPT_KEY, true), 0, $iv);
    return base64_encode($iv . $enc);
}

function decrypt_pppoe(string $ciphertext): string {
    if (empty($ciphertext)) return '';
    try {
        $data = base64_decode($ciphertext);
        $iv   = substr($data, 0, 16);
        $enc  = substr($data, 16);
        $dec  = openssl_decrypt($enc, 'AES-256-CBC',
                                hash('sha256', PPPOE_ENCRYPT_KEY, true), 0, $iv);
        return $dec === false ? '' : $dec;
    } catch (Throwable) {
        return '';
    }
}
