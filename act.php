<?php
// ============================================================
//  KAHFINET - Global Action Handler
// ============================================================
require_once __DIR__ . '/system/init.php';

$action = get('action');

switch ($action) {

    case 'login':
        if (!is_post()) json_res(false, 'Metode tidak valid.');

        $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $username   = trim(post('username'));
        $identifier = $ip . '|' . $username;

        if (login_is_locked($identifier)) {
            $remaining = login_lockout_remaining($identifier);
            json_res(false, "Terlalu banyak percobaan gagal. Coba lagi dalam ±{$remaining} menit.", ['lockout' => true, 'remaining' => $remaining]);
        }

        if (!csrf_verify()) {
            json_res(false, 'Token tidak valid. Silakan refresh halaman.');
        }

        $password = post('password');
        $remember = isset($_POST['remember_me']);

        if (mb_strlen($username) > 50 || mb_strlen($password) > 255) {
            json_res(false, 'Input tidak valid.');
        }

        $user = db_row(
            "SELECT * FROM pengguna WHERE username = ? AND status = 'aktif' LIMIT 1",
            [$username]
        );

        if ($user && password_verify($password, $user['password'])) {
            // Login sukses: reset counter, regenerate session
            login_reset($identifier);
            session_regenerate_id(true);

            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_nama']   = $user['nama'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['user_ip']     = $ip;
            $_SESSION['user_agent']  = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['_last_regen'] = time();

            db_update('pengguna', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

            if ($remember) {
                $token    = generate_token();
                $expire   = date('Y-m-d H:i:s', time() + (REMEMBER_ME_DAYS * 86400));
                $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                db_query("DELETE FROM remember_tokens WHERE pengguna_id = ?", [$user['id']]);
                db_insert('remember_tokens', [
                    'pengguna_id' => $user['id'],
                    'token'       => $token,
                    'expired_at'  => $expire,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                setcookie(COOKIE_NAME, $token, time() + (REMEMBER_ME_DAYS * 86400), '/', '', $isSecure, true);
            }

            flash('success', 'Login berhasil! Selamat datang, ' . $user['nama'] . '.');
            json_res(true, 'Login berhasil.', ['redirect' => BASE_URL . 'index.php']);
        } else {
            // Login gagal: catat percobaan
            login_record_fail($identifier);

            if (login_is_locked($identifier)) {
                $remaining = login_lockout_remaining($identifier);
                json_res(false, "Terlalu banyak percobaan gagal. Akun dikunci ±{$remaining} menit.", ['lockout' => true, 'remaining' => $remaining]);
            }

            $sisaKey  = 'login_attempts_' . md5($identifier);
            $attempts = $_SESSION[$sisaKey] ?? 0;
            $sisa     = LOGIN_MAX_ATTEMPTS - $attempts;
            json_res(false, 'Username atau password salah.' . ($sisa > 0 ? " Sisa percobaan: {$sisa}." : ''), ['sisa' => $sisa]);
        }
        break;

    case 'logout':
        auth_check();
        // Hapus remember token dari DB
        if (isset($_SESSION['user_id'])) {
            db_query("DELETE FROM remember_tokens WHERE pengguna_id = ?", [$_SESSION['user_id']]);
        }
        // Hapus cookie
        setcookie(COOKIE_NAME, '', time() - 3600, '/', '', false, true);
        // Destroy session sepenuhnya
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
        redirect(BASE_URL . 'login.php');
        break;

    default:
        redirect(BASE_URL . 'index.php');
}
