<?php
// ============================================================
//  KAHFINET - Modul ACS (Action Handler)
//  Menangani: pengaturan koneksi, tes koneksi
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/acs/views.php';

switch ($action) {

    case 'save_settings':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $rules = [
            'base_url' => ['label' => 'Base URL', 'required' => true, 'max' => 255],
        ];
        $errs = validate_input($rules, $_POST);
        if ($errs) {
            flash('danger', implode(' ', $errs));
            redirect($back_url);
        }

        $data = [
            'nama'       => trim(post('nama')) ?: 'ACS Utama',
            'base_url'   => rtrim(trim(post('base_url')), '/'),
            'username'   => trim(post('username')) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Password hanya diupdate kalau diisi (biar tidak ketimpa kosong)
        $password = post('password');
        if ($password !== '') {
            $data['password'] = encrypt_pppoe($password);
        }

        $existing = db_row("SELECT id FROM acs_settings WHERE is_active = 1 ORDER BY id LIMIT 1");
        if ($existing) {
            db_update('acs_settings', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['password']  = $data['password'] ?? null;
            $data['is_active'] = 1;
            db_insert('acs_settings', $data);
        }

        flash('success', 'Pengaturan ACS berhasil disimpan.');
        redirect($back_url);

    case 'test_connection':
        $result = acs_test_connection();
        json_res($result['ok'], $result['msg']);

    case 'sync_devices':
        $result = acs_sync_devices();
        flash($result['ok'] ? 'success' : 'warning', $result['msg']);
        redirect(BASE_URL . 'modules/acs/device.php');

    // ── MAPPING MANUAL: Device ONU <-> Secret PPP ──────────────
    case 'set_mapping':
        $deviceBackUrl = BASE_URL . 'modules/acs/device.php';
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($deviceBackUrl); }

        $deviceId = (int)post('device_id');
        $secretId = post('secret_id') !== '' ? (int)post('secret_id') : null;

        $device = db_row("SELECT id FROM genieacs_devices_cache WHERE id = ?", [$deviceId]);
        if (!$device) { flash('danger', 'Device tidak ditemukan.'); redirect($deviceBackUrl); }

        // Lepas dulu secret lain yang kebetulan masih nempel ke device ini (1 device = 1 secret).
        db_update('mikrotik_secrets_cache', ['genieacs_device_id' => null], 'genieacs_device_id = ?', [$deviceId]);

        if ($secretId !== null) {
            $secret = db_row("SELECT id FROM mikrotik_secrets_cache WHERE id = ?", [$secretId]);
            if (!$secret) { flash('danger', 'Secret PPP tidak valid.'); redirect($deviceBackUrl); }

            db_update('mikrotik_secrets_cache', ['genieacs_device_id' => $deviceId], 'id = ?', [$secretId]);
            flash('success', 'Mapping device ONU berhasil disimpan.');
        } else {
            flash('success', 'Mapping device ONU berhasil dihapus.');
        }
        redirect($deviceBackUrl);

    default:
        redirect($back_url);
}
