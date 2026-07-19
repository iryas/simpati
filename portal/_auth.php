<?php
// ============================================================
//  PORTAL PELANGGAN — Auth & Sesi
//  Sesi pelanggan terpisah dari sesi staf (pengguna).
//  Login: No HP + PIN (PIN disimpan ter-hash via password_hash).
// ============================================================

require_once __DIR__ . '/../system/init.php';

// URL dasar portal
if (!defined('PORTAL_URL')) {
    define('PORTAL_URL', BASE_URL . 'portal/');
}

// ── Cari kandidat pelanggan berdasarkan No HP (dinormalkan) ───
//  Nomor tersimpan bisa beragam format (08.., 62.., +62..),
//  jadi dicocokkan lewat format WA yang sudah dinormalkan.
//  Hanya pelanggan yang PIN-nya sudah di-set yang diperhitungkan.
function portal_find_by_hp(string $no_hp): array {
    $target = format_no_hp_wa($no_hp);
    if (strlen($target) < 9) return []; // nomor terlalu pendek / placeholder

    $rows = db_rows(
        "SELECT * FROM pelanggan
         WHERE pin IS NOT NULL AND no_hp IS NOT NULL AND no_hp <> ''"
    );
    $match = [];
    foreach ($rows as $r) {
        if (format_no_hp_wa($r['no_hp']) === $target) {
            $match[] = $r;
        }
    }
    return $match;
}

// ── Verifikasi kredensial login ───────────────────────────────
//  Return row pelanggan bila cocok, atau false.
function portal_verify(string $no_hp, string $pin): array|false {
    foreach (portal_find_by_hp($no_hp) as $p) {
        if (password_verify($pin, (string)$p['pin'])) {
            return $p;
        }
    }
    return false;
}

// ── Buat sesi pelanggan ───────────────────────────────────────
function portal_set_session(array $p): void {
    session_regenerate_id(true);
    $_SESSION['pelanggan_id']    = (int)$p['id'];
    $_SESSION['pelanggan_nama']  = $p['nama'];
    $_SESSION['_portal_regen']   = time();
    db_update('pelanggan', ['portal_last_login' => date('Y-m-d H:i:s')], 'id = ?', [$p['id']]);
}

function portal_is_logged_in(): bool {
    return isset($_SESSION['pelanggan_id']);
}

// ── Guard: wajib login ────────────────────────────────────────
function portal_auth_check(): void {
    if (!portal_is_logged_in()) {
        redirect(PORTAL_URL . 'login.php');
    }
}

// ── Data pelanggan yang sedang login (join paket & area) ──────
function portal_current(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $p = db_row(
        "SELECT p.*,
                pk.nama       AS nama_paket,
                pk.harga      AS harga_paket,
                pk.kecepatan  AS kecepatan_paket,
                a.nama        AS nama_area
         FROM pelanggan p
         LEFT JOIN paket pk ON pk.id = p.paket_id
         LEFT JOIN area  a  ON a.id  = p.area_id
         WHERE p.id = ? LIMIT 1",
        [$_SESSION['pelanggan_id']]
    );

    if (!$p) {
        // Akun terhapus di tengah sesi — paksa logout.
        portal_logout();
        redirect(PORTAL_URL . 'login.php');
    }
    return $cache = $p;
}

// ── Logout ────────────────────────────────────────────────────
function portal_logout(): void {
    unset(
        $_SESSION['pelanggan_id'],
        $_SESSION['pelanggan_nama'],
        $_SESSION['_portal_regen']
    );
}
