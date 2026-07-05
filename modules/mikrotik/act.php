<?php
// ============================================================
//  KAHFINET - Modul Mikrotik (Action Handler)
//  Menangani: pengaturan koneksi, tes koneksi, sync profile & secret
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN, ROLE_TEKNISI]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/mikrotik/views.php';

switch ($action) {

    case 'save_router':
        auth_role([ROLE_ADMIN]);
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

    case 'datatable_secrets':
        $q      = trim(get('search')['value'] ?? '');
        $limit  = max(1, (int)get('length', 15));
        $offset = max(0, (int)get('start', 0));

        $where  = '1=1';
        $params = [];
        if ($q !== '') {
            $where  = "(name LIKE ? OR profile LIKE ? OR remote_address LIKE ? OR comment LIKE ?)";
            $like   = "%$q%";
            $params = [$like, $like, $like, $like];
        }

        $total    = (int)db_row("SELECT COUNT(*) as n FROM mikrotik_secrets_cache WHERE $where", $params)['n'];
        $rows     = db_rows("SELECT * FROM mikrotik_secrets_cache WHERE $where ORDER BY name ASC LIMIT $limit OFFSET $offset", $params);

        $data = [];
        foreach ($rows as $i => $r) {
            $status = $r['disabled']
                ? '<span class="badge badge-secondary">Disabled</span>'
                : '<span class="badge badge-success">Enabled</span>';
            $data[] = [
                'no'             => $offset + count($data) + 1,
                'name'           => clean($r['name']),
                'profile'        => clean($r['profile'] ?? '—'),
                'remote_address' => clean($r['remote_address'] ?? '—'),
                'status'         => $status,
                'comment'        => clean($r['comment'] ?? '—'),
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'draw'            => (int)get('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        redirect($back_url);
}
