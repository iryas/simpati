<?php
// ============================================================
//  KAHFINET - Modul ACS (Action Handler)
//  Menangani: pengaturan koneksi, tes koneksi
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN, ROLE_TEKNISI]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/acs/views.php';

switch ($action) {

    case 'save_settings':
        auth_role([ROLE_ADMIN]);
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

    case 'datatable_devices':
        $q      = trim(get('search')['value'] ?? '');
        $limit  = max(1, (int)get('length', 15));
        $offset = max(0, (int)get('start', 0));

        $where  = '1=1';
        $params = [];
        if ($q !== '') {
            $where  = "(gdc.tag LIKE ? OR gdc.manufacturer LIKE ? OR gdc.product_class LIKE ? OR msc.name LIKE ? OR pl.nama LIKE ?)";
            $like   = "%$q%";
            $params = [$like, $like, $like, $like, $like];
        }

        $total = (int)db_row(
            "SELECT COUNT(*) as n
             FROM genieacs_devices_cache gdc
             LEFT JOIN mikrotik_secrets_cache msc ON msc.genieacs_device_id = gdc.id
             LEFT JOIN pelanggan pl ON pl.mikrotik_secrets_id = msc.id
             WHERE $where",
            $params
        )['n'];

        $rows = db_rows(
            "SELECT gdc.*, msc.id as secret_id, msc.name as secret_name,
                    pl.nama as nama_pelanggan
             FROM genieacs_devices_cache gdc
             LEFT JOIN mikrotik_secrets_cache msc ON msc.genieacs_device_id = gdc.id
             LEFT JOIN pelanggan pl ON pl.mikrotik_secrets_id = msc.id
             WHERE $where
             ORDER BY gdc.tag ASC, gdc.device_id ASC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $data = [];
        foreach ($rows as $r) {
            $model = trim(($r['manufacturer'] ?? '') . ' ' . ($r['product_class'] ?? '')) ?: '—';

            if ($r['secret_id']) {
                $mapping =
                    '<div class="font-weight-bold" style="font-size:13px">' . clean($r['nama_pelanggan'] ?? '—') . '</div>' .
                    '<div class="text-muted" style="font-size:12px">secret: ' . clean($r['secret_name']) . '</div>' .
                    '<button type="button" class="btn btn-outline-secondary btn-xs mt-1 btn-mapping"' .
                    ' data-device-id="' . (int)$r['id'] . '"' .
                    ' data-tag="' . clean($r['tag'] ?? '—') . '"' .
                    ' data-model="' . clean($model) . '"' .
                    ' data-pppoe="' . clean($r['pppoe_username'] ?? '') . '"' .
                    ' data-secret-id="' . (int)$r['secret_id'] . '">Ubah Mapping</button>';
            } else {
                $mapping =
                    '<button type="button" class="btn btn-outline-info btn-xs btn-mapping"' .
                    ' data-device-id="' . (int)$r['id'] . '"' .
                    ' data-tag="' . clean($r['tag'] ?? '—') . '"' .
                    ' data-model="' . clean($model) . '"' .
                    ' data-pppoe="' . clean($r['pppoe_username'] ?? '') . '"' .
                    ' data-secret-id=""><i class="fas fa-link mr-1"></i>Mapping ke...</button>';
            }

            $data[] = [
                'no'          => $offset + count($data) + 1,
                'tag'         => clean($r['tag'] ?? '—'),
                'model'       => '<span class="text-muted">' . clean($model) . '</span>',
                'last_inform' => $r['last_inform'] ? tgl_indo($r['last_inform'], true) : '<span class="text-muted">—</span>',
                'mapping'     => $mapping,
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
