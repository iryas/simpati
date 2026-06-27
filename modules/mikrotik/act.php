<?php
// ============================================================
//  KAHFINET - Modul Mikrotik (Action Handler)
//  Menangani: pengaturan koneksi, tes koneksi, sync profile & secret
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/mikrotik/views.php';

switch ($action) {

    case 'save_router':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $rules = [
            'host'     => ['label' => 'Host/IP', 'required' => true, 'max' => 100],
            'username' => ['label' => 'Username API', 'required' => true, 'max' => 100],
        ];
        $errs = validate_input($rules, $_POST);
        if ($errs) {
            flash('danger', implode(' ', $errs));
            redirect($back_url);
        }

        $data = [
            'nama'     => trim(post('nama')) ?: 'Router Utama',
            'host'     => trim(post('host')),
            'api_port' => (int)post('api_port', 8728) ?: 8728,
            'use_ssl'  => post('use_ssl') ? 1 : 0,
            'username' => trim(post('username')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Password hanya diupdate kalau diisi (biar ga ketimpa kosong)
        $password = post('password');
        if ($password !== '') {
            $data['password'] = encrypt_pppoe($password);
        }

        $existing = db_row("SELECT id FROM mikrotik_routers WHERE is_active = 1 ORDER BY id LIMIT 1");
        if ($existing) {
            db_update('mikrotik_routers', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['password']  = $data['password'] ?? '';
            $data['is_active'] = 1;
            db_insert('mikrotik_routers', $data);
        }

        flash('success', 'Konfigurasi router berhasil disimpan.');
        redirect($back_url);

    case 'test_connection':
        $api = mikrotik_client();
        if (!$api) {
            json_res(false, 'Gagal konek ke router. Cek host, port, dan kredensial.');
        }
        try {
            $rows = $api->comm('/system/identity/print');
            $api->close();
            json_res(true, 'Berhasil konek ke router: ' . ($rows[0]['name'] ?? '(tidak diketahui)'));
        } catch (Throwable $e) {
            json_res(false, 'Gagal komunikasi dengan router: ' . $e->getMessage());
        }

    case 'sync_profiles':
        $result = mikrotik_sync_profiles();
        flash($result['ok'] ? 'success' : 'warning', $result['msg']);
        redirect(BASE_URL . 'modules/mikrotik/profile.php');

    case 'sync_secrets':
        $result = mikrotik_sync_secrets();
        flash($result['ok'] ? 'success' : 'warning', $result['msg']);
        redirect(BASE_URL . 'modules/mikrotik/secret.php');

    default:
        redirect($back_url);
}
