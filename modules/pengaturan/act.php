<?php
// ============================================================
//  KAHFINET - Modul Pengaturan Aplikasi (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/pengaturan/views.php';

switch ($action) {

    case 'save':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $tgl = (int)post('tgl_mulai_tagihan');
        if ($tgl < 1 || $tgl > 28) {
            flash('danger', 'Tanggal mulai tagihan harus antara 1 dan 28.');
            redirect($back_url);
        }

        $nama_isp = mb_substr(trim(post('nama_isp')), 0, 100);
        if ($nama_isp === '') {
            flash('danger', 'Nama ISP tidak boleh kosong.');
            redirect($back_url);
        }

        $grace  = max(0, min(30, (int)post('grace_period_isolir')));
        $no_cs  = mb_substr(trim(post('no_cs')), 0, 20);

        $profile_isolir = mb_substr(trim(post('mikrotik_profile_isolir')), 0, 100);
        if ($profile_isolir === '') $profile_isolir = 'profile-Isolir2';

        $updates = [
            'tgl_mulai_tagihan'      => (string)$tgl,
            'nama_isp'               => $nama_isp,
            'grace_period_isolir'    => (string)$grace,
            'no_cs'                  => $no_cs,
            'mikrotik_profile_isolir' => $profile_isolir,
        ];

        foreach ($updates as $key => $val) {
            db_query(
                "INSERT INTO app_settings (setting_key, setting_val)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), updated_at = NOW()",
                [$key, $val]
            );
        }

        flash('success', 'Pengaturan berhasil disimpan.');
        redirect($back_url);

    case 'save_wa':
    case 'save_wablas': // backward compat
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $wablas_aktif  = post('wablas_aktif') === '1' ? '1' : '0';
        $wa_gateway    = in_array(post('wa_gateway'), ['wablas', 'fonnte']) ? post('wa_gateway') : 'wablas';
        $wablas_token  = mb_substr(trim(post('wablas_token')), 0, 500);
        $wablas_secret = mb_substr(trim(post('wablas_secret')), 0, 500);
        $fonnte_token  = mb_substr(trim(post('fonnte_token')), 0, 500);

        foreach ([
            'wablas_aktif'  => $wablas_aktif,
            'wa_gateway'    => $wa_gateway,
            'wablas_token'  => $wablas_token,
            'wablas_secret' => $wablas_secret,
            'fonnte_token'  => $fonnte_token,
        ] as $key => $val) {
            db_query(
                "INSERT INTO app_settings (setting_key, setting_val)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), updated_at = NOW()",
                [$key, $val]
            );
        }

        flash('success', 'Pengaturan WhatsApp Gateway berhasil disimpan.');
        redirect($back_url);

    default:
        redirect($back_url);
}
