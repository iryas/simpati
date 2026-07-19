<?php
// ============================================================
//  PORTAL PELANGGAN — Action Handler
// ============================================================
require_once __DIR__ . '/_auth.php';

$action = get('action');

switch ($action) {

    // ── Login (No HP + PIN) ───────────────────────────────────
    case 'login':
        if (!is_post())      json_res(false, 'Metode tidak valid.');
        if (!csrf_verify())  json_res(false, 'Sesi kedaluwarsa. Muat ulang halaman.');

        $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $no_hp      = trim(post('no_hp'));
        $pin        = trim(post('pin'));
        $identifier = 'portal|' . $ip . '|' . format_no_hp_wa($no_hp);

        if (login_is_locked($identifier)) {
            $sisa = login_lockout_remaining($identifier);
            json_res(false, "Terlalu banyak percobaan. Coba lagi dalam ±{$sisa} menit.", ['lockout' => true]);
        }

        // Validasi bentuk input
        if (!preg_match('/^[0-9]{6}$/', $pin)) {
            json_res(false, 'PIN harus 6 digit angka.');
        }
        if (mb_strlen($no_hp) < 8 || mb_strlen($no_hp) > 20) {
            json_res(false, 'Nomor HP tidak valid.');
        }

        $p = portal_verify($no_hp, $pin);
        if ($p) {
            login_reset($identifier);
            portal_set_session($p);
            json_res(true, 'Login berhasil.', ['redirect' => PORTAL_URL . 'index.php']);
        }

        login_record_fail($identifier);
        if (login_is_locked($identifier)) {
            $sisa = login_lockout_remaining($identifier);
            json_res(false, "Terlalu banyak percobaan. Akun dikunci ±{$sisa} menit.", ['lockout' => true]);
        }
        json_res(false, 'Nomor HP atau PIN salah.');
        break;

    // ── Logout ────────────────────────────────────────────────
    case 'logout':
        portal_logout();
        redirect(PORTAL_URL . 'login.php');
        break;

    // ── Ganti PIN ─────────────────────────────────────────────
    case 'ganti_pin':
        portal_auth_check();
        if (!is_post() || !csrf_verify()) {
            flash('danger', 'Permintaan tidak valid.');
            redirect(PORTAL_URL . 'profil.php');
        }

        $pin_lama  = trim(post('pin_lama'));
        $pin_baru  = trim(post('pin_baru'));
        $pin_ulang = trim(post('pin_ulang'));

        $p = db_row("SELECT pin FROM pelanggan WHERE id = ? LIMIT 1", [$_SESSION['pelanggan_id']]);

        if (!$p || !password_verify($pin_lama, (string)$p['pin'])) {
            flash('danger', 'PIN lama tidak sesuai.');
            redirect(PORTAL_URL . 'profil.php');
        }
        if (!preg_match('/^[0-9]{6}$/', $pin_baru)) {
            flash('danger', 'PIN baru harus 6 digit angka.');
            redirect(PORTAL_URL . 'profil.php');
        }
        if ($pin_baru !== $pin_ulang) {
            flash('danger', 'Konfirmasi PIN tidak cocok.');
            redirect(PORTAL_URL . 'profil.php');
        }

        db_update('pelanggan', [
            'pin'            => password_hash($pin_baru, PASSWORD_DEFAULT),
            'pin_updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$_SESSION['pelanggan_id']]);

        flash('success', 'PIN berhasil diubah.');
        redirect(PORTAL_URL . 'profil.php');
        break;

    // ── Lapor gangguan ────────────────────────────────────────
    case 'lapor':
        portal_auth_check();
        if (!is_post() || !csrf_verify()) {
            flash('danger', 'Permintaan tidak valid.');
            redirect(PORTAL_URL . 'lapor.php');
        }

        $kategori_valid = ['koneksi_lambat', 'tidak_konek', 'perangkat', 'tagihan', 'lainnya'];
        $kategori  = post('kategori');
        $deskripsi = trim(post('deskripsi'));

        if (!in_array($kategori, $kategori_valid, true)) {
            flash('danger', 'Kategori tidak valid.');
            redirect(PORTAL_URL . 'lapor.php');
        }
        if (mb_strlen($deskripsi) < 10) {
            flash('danger', 'Ceritakan keluhan minimal 10 karakter agar jelas.');
            redirect(PORTAL_URL . 'lapor.php');
        }
        if (mb_strlen($deskripsi) > 1000) {
            $deskripsi = mb_substr($deskripsi, 0, 1000);
        }

        // Batasi tiket terbuka agar tidak spam (maks 3 tiket aktif).
        $aktif = db_row(
            "SELECT COUNT(*) c FROM tiket_gangguan
             WHERE pelanggan_id = ? AND status IN ('baru','diproses')",
            [$_SESSION['pelanggan_id']]
        );
        if ((int)$aktif['c'] >= 3) {
            flash('warning', 'Anda masih punya laporan yang sedang diproses. Mohon tunggu ditangani dulu.');
            redirect(PORTAL_URL . 'lapor.php');
        }

        db_insert('tiket_gangguan', [
            'pelanggan_id' => $_SESSION['pelanggan_id'],
            'kategori'     => $kategori,
            'deskripsi'    => $deskripsi,
            'status'       => 'baru',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        flash('success', 'Laporan terkirim. Tim kami akan segera menindaklanjuti. 🙏');
        redirect(PORTAL_URL . 'lapor.php');
        break;

    default:
        redirect(PORTAL_URL . 'index.php');
}
