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
        redirect($back_url . '?tab=umum');

    case 'save_fup':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url . '?tab=fup');
        }

        $fup_aktif = post('fup_aktif') === '1' ? '1' : '0';

        $kuota_harian = (float)post('kuota_harian_gb');
        if ($kuota_harian < 0 || $kuota_harian > 1000) {
            flash('danger', 'Kuota harian FUP tidak valid (0-1000 GB).');
            redirect($back_url . '?tab=fup');
        }

        $profile_fup = mb_substr(trim(post('mikrotik_profile_fup')), 0, 100);
        if ($profile_fup === '') $profile_fup = 'profile-FUP';

        foreach ([
            'fup_aktif'            => $fup_aktif,
            'kuota_harian_gb'      => (string)$kuota_harian,
            'mikrotik_profile_fup' => $profile_fup,
        ] as $key => $val) {
            db_query(
                "INSERT INTO app_settings (setting_key, setting_val)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), updated_at = NOW()",
                [$key, $val]
            );
        }

        flash('success', 'Pengaturan FUP berhasil disimpan.');
        redirect($back_url . '?tab=fup');

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
        redirect($back_url . '?tab=wa');

    case 'save_telegram':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $telegram_aktif     = post('telegram_aktif') === '1' ? '1' : '0';
        $telegram_bot_token = mb_substr(trim(post('telegram_bot_token')), 0, 200);
        $telegram_chat_id   = mb_substr(trim(post('telegram_chat_id')), 0, 60);

        foreach ([
            'telegram_aktif'     => $telegram_aktif,
            'telegram_bot_token' => $telegram_bot_token,
            'telegram_chat_id'   => $telegram_chat_id,
        ] as $key => $val) {
            db_query(
                "INSERT INTO app_settings (setting_key, setting_val)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), updated_at = NOW()",
                [$key, $val]
            );
        }

        flash('success', 'Pengaturan Telegram berhasil disimpan.');
        redirect($back_url . '?tab=telegram');

    case 'test_telegram':
        if (!csrf_verify()) {
            json_res(false, 'Sesi kedaluwarsa, muat ulang halaman.');
        }
        $token  = trim(post('telegram_bot_token'));
        $chatId = trim(post('telegram_chat_id'));
        $result = telegram_test($token, $chatId);
        json_res($result['ok'], $result['msg']);

    default:
        redirect($back_url);
}
