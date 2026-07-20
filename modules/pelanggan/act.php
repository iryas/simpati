<?php
// ============================================================
//  KAHFINET - Modul Pelanggan (Action Handler - Diperkuat)
//  Pelanggan adalah data detail/bisnis yang menempel (1:1) ke satu
//  PPP Secret yang sudah disync dari Mikrotik (mikrotik_secrets_cache).
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/pelanggan/views.php';

switch ($action) {

    // ── DATATABLE STATUS (server-side, untuk halaman Status Pelanggan) ──
    case 'datatable_status':
        $draw   = (int)get('draw', 1);
        $start  = max(0, (int)get('start', 0));
        $length = (int)get('length', 10);
        $length = $length > 0 ? min($length, 100) : 10;
        $search = trim($_GET['search']['value'] ?? '');
        $status = get('status_filter');
        $areaId = (int)get('area_filter');

        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (pl.nama LIKE ? OR pl.no_hp LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($status) {
            $where   .= ' AND pl.status = ?';
            $params[] = $status;
        }
        if ($areaId) {
            $where   .= ' AND pl.area_id = ?';
            $params[] = $areaId;
        }

        $recordsTotal = (int)db_row("SELECT COUNT(*) as n FROM pelanggan")['n'];

        $recordsFiltered = (int)db_row(
            "SELECT COUNT(*) as n FROM pelanggan pl WHERE $where",
            $params
        )['n'];

        $rows = db_rows(
            "SELECT pl.*, ar.nama as nama_area, pk.nama as nama_paket
             FROM pelanggan pl
             LEFT JOIN area ar ON ar.id = pl.area_id
             LEFT JOIN paket pk ON pk.id = pl.paket_id
             WHERE $where
             ORDER BY FIELD(pl.status, 'isolir', 'nonaktif', 'aktif') ASC, pl.nama ASC
             LIMIT $length OFFSET $start",
            $params
        );

        $canChange = current_user()['role'] === ROLE_ADMIN;
        $data = [];

        foreach ($rows as $i => $r) {
            if ($canChange) {
                $statusCell = '<select class="form-control form-control-sm select-status-pl" style="width:130px" '
                    . 'data-id="' . (int)$r['id'] . '" data-nama="' . clean($r['nama']) . '" data-current="' . clean($r['status']) . '">'
                    . '<option value="aktif"' . ($r['status'] === 'aktif' ? ' selected' : '') . '>Aktif</option>'
                    . '<option value="nonaktif"' . ($r['status'] === 'nonaktif' ? ' selected' : '') . '>Non-aktif</option>'
                    . '<option value="isolir"' . ($r['status'] === 'isolir' ? ' selected' : '') . '>Isolir</option>'
                    . '</select>';
            } else {
                $statusCell = badge_status($r['status']);
            }

            $data[] = [
                'no'         => $start + $i + 1,
                'nama'       => '<span class="font-weight-bold">' . clean($r['nama']) . '</span>',
                'area'       => clean($r['nama_area'] ?? '—'),
                'paket'      => clean($r['nama_paket'] ?? '—'),
                'status'     => $statusCell,
                'status_raw' => $r['status'],
                'updated_at' => $r['updated_at'] ? tgl_indo($r['updated_at']) : '—',
                'aksi'       => '<button class="btn btn-secondary btn-xs btn-riwayat-status" data-id="' . (int)$r['id'] . '" title="Riwayat Status"><i class="fas fa-history"></i></button>',
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
        exit;

    // ── UPDATE STATUS (form sederhana, dari halaman Status Pelanggan) ──
    case 'update_status':
        auth_role([ROLE_ADMIN]);
        $statusBackUrl = BASE_URL . 'modules/pelanggan/status.php';
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($statusBackUrl); }

        $id         = (int)post('id');
        $newStatus  = post('status');
        $newPaketId = post('paket_id') !== '' ? (int)post('paket_id') : null;
        $keterangan = mb_substr(trim(post('keterangan')), 0, 255);
        if (!in_array($newStatus, ['aktif', 'nonaktif', 'isolir'], true)) {
            flash('danger', 'Status tidak valid.');
            redirect($statusBackUrl);
        }

        $row = db_row("SELECT id, nama, status, paket_id, mikrotik_secrets_id FROM pelanggan WHERE id = ?", [$id]);
        if (!$row) { flash('danger', 'Pelanggan tidak ditemukan.'); redirect($statusBackUrl); }

        $statusChanged = $row['status'] !== $newStatus;
        $paketChanged  = $newPaketId !== null && $newPaketId !== (int)$row['paket_id'];
        $pesan = [];

        if ($statusChanged) {
            db_update('pelanggan', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            db_insert('pelanggan_status_log', [
                'pelanggan_id' => $id,
                'tipe'         => 'status',
                'keterangan'   => $keterangan !== '' ? $keterangan : null,
                'details'      => json_encode(['lama' => $row['status'], 'baru' => $newStatus]),
                'diubah_oleh'  => current_user()['id'],
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $pesan[] = 'status';
        }

        if ($paketChanged) {
            $paketLama = $row['paket_id'] ? db_row("SELECT nama FROM paket WHERE id = ?", [$row['paket_id']]) : null;
            $paketBaru = db_row("SELECT nama FROM paket WHERE id = ?", [$newPaketId]);
            if (!$paketBaru) { flash('danger', 'Paket tidak valid.'); redirect($statusBackUrl); }

            db_update('pelanggan', ['paket_id' => $newPaketId, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            db_insert('pelanggan_status_log', [
                'pelanggan_id' => $id,
                'tipe'         => 'paket',
                'keterangan'   => $keterangan !== '' ? $keterangan : null,
                'details'      => json_encode([
                    'lama' => $paketLama['nama'] ?? null,
                    'baru' => $paketBaru['nama'],
                ]),
                'diubah_oleh'  => current_user()['id'],
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $pesan[] = 'paket';
        }

        // ── Push profile + disabled ke secret PPP di Mikrotik ──
        // aktif    -> profile paket asli, disabled=no
        // isolir   -> app_setting mikrotik_profile_isolir, disabled=no
        // nonaktif -> app_setting mikrotik_profile_isolir, disabled=yes (akun dimatikan total)
        $mikrotikWarning = '';
        if (($statusChanged || $paketChanged) && $row['mikrotik_secrets_id']) {
            $finalPaketId = $paketChanged ? $newPaketId : (int)$row['paket_id'];
            $disabled     = $newStatus === 'nonaktif';

            $secret = db_row("SELECT ros_id, name, genieacs_device_id FROM mikrotik_secrets_cache WHERE id = ?", [$row['mikrotik_secrets_id']]);

            if ($newStatus === 'aktif') {
                $profileRow  = $finalPaketId
                    ? db_row("SELECT mpc.name FROM paket pk JOIN mikrotik_profiles_cache mpc ON mpc.id = pk.mikrotik_profiles_id WHERE pk.id = ?", [$finalPaketId])
                    : null;
                $profileName = $profileRow['name'] ?? null;
            } else {
                $profileName = app_setting('mikrotik_profile_isolir', 'profile-Isolir2');
            }

            if ($secret && $profileName) {
                $ok = mikrotik_secret_push_profile($secret['ros_id'], $profileName, $disabled, $secret['name'] ?? '');
                if (!$ok) {
                    $mikrotikWarning = ' Namun gagal sync ke Mikrotik (cek koneksi router).';
                }
            } else {
                $mikrotikWarning = ' Namun tidak bisa sync ke Mikrotik: secret atau profile paket belum terhubung.';
            }

            // Reboot ONU/ONT supaya perubahan profile/status langsung kepakai,
            // tanpa pelanggan harus restart manual perangkatnya sendiri.
            if ($secret && $secret['genieacs_device_id']) {
                $device = db_row("SELECT device_id FROM genieacs_devices_cache WHERE id = ?", [$secret['genieacs_device_id']]);
                if ($device) {
                    $rebootOk = acs_reboot_device($device['device_id']);
                    if (!$rebootOk) {
                        $mikrotikWarning .= ' Gagal kirim reboot ke ONU (cek koneksi ACS).';
                    }
                }
            }
        }

        if ($pesan) {
            flash($mikrotikWarning ? 'warning' : 'success', 'Perubahan ' . implode(' & ', $pesan) . ' pelanggan ' . $row['nama'] . ' berhasil disimpan.' . $mikrotikWarning);
        } else {
            flash('warning', 'Tidak ada perubahan untuk pelanggan ' . $row['nama'] . '.');
        }
        redirect($statusBackUrl);

    // ── GET RIWAYAT STATUS ─────────────────────────────────────
    case 'get_status_log':
        $id = (int)get('id');
        $rows = db_rows(
            "SELECT l.*, u.nama as nama_user
             FROM pelanggan_status_log l
             LEFT JOIN pengguna u ON u.id = l.diubah_oleh
             WHERE l.pelanggan_id = ?
             ORDER BY l.id DESC",
            [$id]
        );
        json_res(true, '', $rows);

    // ── DATATABLE (server-side) ───────────────────────────────
    case 'datatable':
        $draw   = (int)get('draw', 1);
        $start  = max(0, (int)get('start', 0));
        $length = (int)get('length', 10);
        $length = $length > 0 ? min($length, 100) : 10;
        $search = trim($_GET['search']['value'] ?? '');
        $status = get('status_filter');
        $areaId = (int)get('area_filter');

        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (pl.nama LIKE ? OR pl.no_hp LIKE ? OR pl.alamat LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($status) {
            $where   .= ' AND pl.status = ?';
            $params[] = $status;
        }
        if ($areaId) {
            $where   .= ' AND pl.area_id = ?';
            $params[] = $areaId;
        }

        $orderCols = [1 => 'pl.nama', 2 => 'pl.no_hp', 3 => 'pl.alamat', 4 => 'ar.nama', 5 => 'pk.nama', 6 => 'pl.status'];
        $orderCol  = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir  = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $orderBy   = $orderCols[$orderCol] ?? 'pl.id';

        $recordsTotal = (int)db_row("SELECT COUNT(*) as n FROM pelanggan")['n'];

        $recordsFiltered = (int)db_row(
            "SELECT COUNT(*) as n FROM pelanggan pl
             LEFT JOIN paket pk ON pk.id = pl.paket_id
             LEFT JOIN area ar ON ar.id = pl.area_id
             WHERE $where",
            $params
        )['n'];

        $rows = db_rows(
            "SELECT pl.*, pk.nama as nama_paket, ar.nama as nama_area, msc.name as username_pppoe
             FROM pelanggan pl
             LEFT JOIN paket pk ON pk.id = pl.paket_id
             LEFT JOIN area ar ON ar.id = pl.area_id
             LEFT JOIN mikrotik_secrets_cache msc ON msc.id = pl.mikrotik_secrets_id
             WHERE $where
             ORDER BY $orderBy $orderDir
             LIMIT $length OFFSET $start",
            $params
        );

        $canEdit   = in_array(current_user()['role'], [ROLE_ADMIN, ROLE_KEUANGAN]);
        $canDelete = current_user()['role'] === ROLE_ADMIN;
        $data = [];

        foreach ($rows as $i => $r) {
            $namaCell = '<div class="font-weight-bold" style="font-size:13.5px">' . clean($r['nama']) . '</div>';
            if ($r['username_pppoe']) {
                $namaCell .= '<small class="text-muted">' . clean($r['username_pppoe']) . '</small>';
            }

            $aksi = '<button class="btn btn-info btn-xs btn-detail-pelanggan" data-id="' . (int)$r['id'] . '" data-toggle="modal" data-target="#modalDetail" title="Detail"><i class="fas fa-eye"></i></button> ';
            if ($canEdit) {
                $aksi .= '<button class="btn btn-warning btn-xs btn-edit-pelanggan" data-id="' . (int)$r['id'] . '" data-toggle="modal" data-target="#modalEdit"><i class="fas fa-edit"></i></button> ';
            }
            if ($canDelete) {
                $aksi .= '<a href="' . BASE_URL . 'modules/pelanggan/act.php?action=delete&id=' . (int)$r['id'] . '" class="btn btn-danger btn-xs btn-hapus" data-label="pelanggan ' . clean($r['nama']) . '"><i class="fas fa-trash"></i></a>';
            }

            $data[] = [
                'no'     => $start + $i + 1,
                'nama'   => $namaCell,
                'no_hp'  => clean($r['no_hp']),
                'alamat' => clean($r['alamat']),
                'area'   => clean($r['nama_area'] ?? '—'),
                'paket'  => clean($r['nama_paket'] ?? '—'),
                'status' => badge_status($r['status']),
                'aksi'   => $aksi,
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
        exit;

    // ── GET DETAIL + RIWAYAT PEMBAYARAN (untuk modal detail) ──
    case 'get_detail':
        $id = (int)get('id');
        $pelanggan = db_row(
            "SELECT pl.*, ar.nama as nama_area, pk.nama as nama_paket, msc.name as username_pppoe,
                    gdc.id as genieacs_device_id, gdc.tag as device_tag, gdc.last_inform as device_last_inform
             FROM pelanggan pl
             LEFT JOIN area ar ON ar.id = pl.area_id
             LEFT JOIN paket pk ON pk.id = pl.paket_id
             LEFT JOIN mikrotik_secrets_cache msc ON msc.id = pl.mikrotik_secrets_id
             LEFT JOIN genieacs_devices_cache gdc ON gdc.id = msc.genieacs_device_id
             WHERE pl.id = ?",
            [$id]
        );
        if (!$pelanggan) json_res(false, 'Pelanggan tidak ditemukan.');

        $riwayat = db_rows(
            "SELECT py.*, u.nama as nama_kasir
             FROM pembayaran py
             LEFT JOIN pengguna u ON u.id = py.kasir_id
             WHERE py.pelanggan_id = ?
             ORDER BY py.bulan_tagihan DESC, py.id DESC",
            [$id]
        );

        $statusLog = db_rows(
            "SELECT l.*, u.nama as nama_user
             FROM pelanggan_status_log l
             LEFT JOIN pengguna u ON u.id = l.diubah_oleh
             WHERE l.pelanggan_id = ?
             ORDER BY l.id DESC",
            [$id]
        );

        $pelanggan['has_pin'] = !empty($pelanggan['pin']);
        unset($pelanggan['pin']); // jangan bocorkan hash PIN ke klien

        json_res(true, '', ['pelanggan' => $pelanggan, 'riwayat' => $riwayat, 'status_log' => $statusLog]);

    // ── RESET / BUAT PIN PORTAL PELANGGAN ─────────────────────
    case 'reset_pin':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) json_res(false, 'Token tidak valid.');

        $id  = (int)post('id');
        $row = db_row("SELECT id, nama, no_hp FROM pelanggan WHERE id = ? LIMIT 1", [$id]);
        if (!$row) json_res(false, 'Pelanggan tidak ditemukan.');

        $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        db_update('pelanggan', [
            'pin'            => password_hash($pin, PASSWORD_DEFAULT),
            'pin_updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $waInfo = 'off';
        if (isset($_POST['kirim_wa'])) {
            if (empty($row['no_hp']) || strlen(preg_replace('/[^0-9]/', '', $row['no_hp'])) < 9) {
                $waInfo = 'no_hp';
            } else {
                $nama_isp = app_setting('nama_isp', 'KahfiNet');
                $pesan  = "*PIN Portal Pelanggan* — {$nama_isp}\n\n";
                $pesan .= "Halo " . $row['nama'] . ", berikut akses masuk *Portal Pelanggan*:\n\n";
                $pesan .= "No HP : " . $row['no_hp'] . "\n";
                $pesan .= "PIN   : " . $pin . "\n\n";
                $pesan .= "Login di: " . BASE_URL . "portal/\n";
                $pesan .= "Mohon jaga kerahasiaan PIN. Anda bisa menggantinya setelah login.";
                try {
                    $res    = kirim_wa($row['no_hp'], $pesan);
                    $waInfo = ($res['ok'] ?? false) ? 'sent' : 'failed';
                } catch (Throwable $e) {
                    $waInfo = 'failed';
                }
            }
        }

        json_res(true, 'PIN portal berhasil dibuat.', ['pin' => $pin, 'wa' => $waInfo, 'nama' => $row['nama']]);

    // ── REBOOT ONU (lewat ACS, dari modal Detail Pelanggan) ───
    case 'reboot_onu':
        auth_role([ROLE_ADMIN, ROLE_TEKNISI]);
        if (!csrf_verify()) json_res(false, 'Token tidak valid.');

        $id = (int)post('id');
        $row = db_row(
            "SELECT gdc.device_id
             FROM pelanggan pl
             LEFT JOIN mikrotik_secrets_cache msc ON msc.id = pl.mikrotik_secrets_id
             LEFT JOIN genieacs_devices_cache gdc ON gdc.id = msc.genieacs_device_id
             WHERE pl.id = ?",
            [$id]
        );
        if (!$row || !$row['device_id']) {
            json_res(false, 'Device ONU pelanggan ini belum dimapping. Mapping dulu lewat menu ACS > Device ONU.');
        }

        $ok = acs_reboot_device($row['device_id']);
        if ($ok) {
            json_res(true, 'Perintah reboot berhasil dikirim ke ONU.');
        } else {
            json_res(false, 'Gagal kirim perintah reboot. Cek koneksi ke ACS.');
        }

    // ── ISOLIR SATU PELANGGAN ────────────────────────────────
    case 'isolir':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) json_res(false, 'Token tidak valid.');

        $id  = (int)post('id');
        if (!$id) json_res(false, 'ID tidak valid.');

        $row = db_row(
            "SELECT pl.id, pl.nama, pl.no_hp, pl.status,
                    pk.nama as nama_paket,
                    py.bulan_tagihan, py.jumlah,
                    msc.ros_id as mt_ros_id, msc.name as mt_secret_name, gdc.device_id as acs_device_id
             FROM pelanggan pl
             LEFT JOIN paket pk ON pk.id = pl.paket_id
             LEFT JOIN pembayaran py ON py.pelanggan_id = pl.id
                 AND py.status = 'belum' AND py.bulan_tagihan = DATE_FORMAT(NOW(),'%Y-%m')
             LEFT JOIN mikrotik_secrets_cache msc ON msc.id = pl.mikrotik_secrets_id
             LEFT JOIN genieacs_devices_cache gdc ON gdc.id = msc.genieacs_device_id
             WHERE pl.id = ?",
            [$id]
        );
        if (!$row) json_res(false, 'Pelanggan tidak ditemukan.');
        if ($row['status'] === 'isolir') json_res(false, 'Pelanggan sudah berstatus isolir.');

        if (!mikrotik_is_online()) json_res(false, 'Mikrotik tidak dapat dijangkau. Pastikan router online sebelum isolir.');
        $acs_cek = acs_test_connection();
        if (!$acs_cek['ok']) json_res(false, 'ACS tidak dapat dijangkau. Pastikan ACS online sebelum isolir.');

        db_update('pelanggan', ['status' => 'isolir'], 'id = ?', [$id]);
        db_insert('pelanggan_status_log', [
            'pelanggan_id' => $id,
            'tipe'         => 'status',
            'details'      => json_encode(['lama' => $row['status'], 'baru' => 'isolir']),
            'diubah_oleh'  => current_user()['id'],
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        if ($row['mt_ros_id']) {
            mikrotik_secret_push_profile($row['mt_ros_id'], app_setting('mikrotik_profile_isolir', 'profile-Isolir2'), false, $row['mt_secret_name'] ?? '');
        }
        if ($row['acs_device_id']) {
            acs_reboot_device($row['acs_device_id']);
        }
        json_res(true, 'Pelanggan ' . $row['nama'] . ' berhasil diisolir.');

    // ── ISOLIR MASSAL ─────────────────────────────────────────
    case 'isolir_massal':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) json_res(false, 'Token tidak valid.');

        $ids = array_filter(array_map('intval', (array)(post('ids') ?: [])));
        if (!$ids) json_res(false, 'Pilih minimal satu pelanggan.');

        if (!mikrotik_is_online()) json_res(false, 'Mikrotik tidak dapat dijangkau. Pastikan router online sebelum isolir.');
        $acs_cek = acs_test_connection();
        if (!$acs_cek['ok']) json_res(false, 'ACS tidak dapat dijangkau. Pastikan ACS online sebelum isolir.');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows_massal  = db_rows(
            "SELECT pl.id, pl.nama, pl.no_hp, pl.status,
                    pk.nama as nama_paket,
                    py.bulan_tagihan, py.jumlah,
                    msc.ros_id as mt_ros_id, msc.name as mt_secret_name, gdc.device_id as acs_device_id
             FROM pelanggan pl
             LEFT JOIN paket pk ON pk.id = pl.paket_id
             LEFT JOIN pembayaran py ON py.pelanggan_id = pl.id
                 AND py.status = 'belum' AND py.bulan_tagihan = DATE_FORMAT(NOW(),'%Y-%m')
             LEFT JOIN mikrotik_secrets_cache msc ON msc.id = pl.mikrotik_secrets_id
             LEFT JOIN genieacs_devices_cache gdc ON gdc.id = msc.genieacs_device_id
             WHERE pl.id IN ($placeholders) AND pl.status = 'aktif'",
            array_values($ids)
        );

        $diproses = 0;
        $now      = date('Y-m-d H:i:s');
        $kasir_id = current_user()['id'];
        foreach ($rows_massal as $r) {
            db_update('pelanggan', ['status' => 'isolir'], 'id = ?', [$r['id']]);
            db_insert('pelanggan_status_log', [
                'pelanggan_id' => $r['id'],
                'tipe'         => 'status',
                'details'      => json_encode(['lama' => $r['status'], 'baru' => 'isolir']),
                'diubah_oleh'  => $kasir_id,
                'created_at'   => $now,
            ]);
            if ($r['mt_ros_id']) {
                mikrotik_secret_push_profile($r['mt_ros_id'], app_setting('mikrotik_profile_isolir', 'profile-Isolir2'), false, $r['mt_secret_name'] ?? '');
            }
            if ($r['acs_device_id']) {
                acs_reboot_device($r['acs_device_id']);
            }
            $diproses++;
        }
        json_res(true, "$diproses pelanggan berhasil diisolir.");

    // ── GET JSON (untuk modal edit) ───────────────────────────
    case 'get_json':
        auth_role([ROLE_ADMIN, ROLE_KEUANGAN]);
        $id  = (int)get('id');
        $row = db_row("SELECT * FROM pelanggan WHERE id = ?", [$id]);
        if (!$row) json_res(false, 'Data tidak ditemukan.');
        json_res(true, '', $row);

    // ── CREATE ────────────────────────────────────────────────
    case 'create':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }

        $secretId  = (int)post('mikrotik_secrets_id');
        $secretRow = $secretId ? db_row("SELECT id FROM mikrotik_secrets_cache WHERE id = ?", [$secretId]) : null;
        if (!$secretRow) {
            flash('danger', 'Secret PPP wajib dipilih dan harus valid. Sync dulu dari menu Mikrotik > Secret kalau belum ada datanya.');
            redirect($back_url);
        }
        $usedBy = db_row("SELECT id FROM pelanggan WHERE mikrotik_secrets_id = ?", [$secretId]);
        if ($usedBy) {
            flash('danger', 'Secret PPP ini sudah dipakai pelanggan lain.');
            redirect($back_url);
        }

        // Validasi server-side
        $rules = [
            'nama'    => ['label' => 'Nama', 'required' => true, 'max' => 150],
            'no_hp'   => ['label' => 'No HP', 'max' => 20,
                          'regex' => '/^[0-9\-\+\s]*$/'],
            'alamat'  => ['label' => 'Alamat', 'max' => 500],
        ];
        $errs = validate_input($rules, $_POST);
        if ($errs) {
            flash('danger', implode(' ', $errs));
            redirect($back_url);
        }

        $data = [
            'mikrotik_secrets_id' => $secretId,
            'nama'            => trim(post('nama')),
            'no_hp'           => trim(post('no_hp')),
            'alamat'          => trim(post('alamat')),
            'area_id'         => post('area_id') ?: null,
            'paket_id'        => post('paket_id') ?: null,
            'tgl_daftar'      => post('tgl_daftar') ?: date('Y-m-d'),
            'status'          => in_array(post('status'), ['aktif','nonaktif','isolir']) ? post('status') : 'aktif',
            'keterangan'      => mb_substr(trim(post('keterangan')), 0, 500),
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        $newId = db_insert('pelanggan', $data);
        db_insert('pelanggan_status_log', [
            'pelanggan_id' => $newId,
            'tipe'         => 'status',
            'details'      => json_encode(['lama' => null, 'baru' => $data['status']]),
            'diubah_oleh'  => current_user()['id'],
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        flash('success', 'Pelanggan berhasil ditambahkan.');
        redirect($back_url);

    // ── UPDATE ────────────────────────────────────────────────
    case 'update':
        auth_role([ROLE_ADMIN, ROLE_KEUANGAN]);
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }

        $id = (int)post('id');
        if (!$id) { flash('danger', 'ID tidak valid.'); redirect($back_url); }

        $secretId  = (int)post('mikrotik_secrets_id');
        $secretRow = $secretId ? db_row("SELECT id FROM mikrotik_secrets_cache WHERE id = ?", [$secretId]) : null;
        if (!$secretRow) {
            flash('danger', 'Secret PPP wajib dipilih dan harus valid. Sync dulu dari menu Mikrotik > Secret kalau belum ada datanya.');
            redirect($back_url);
        }
        $usedBy = db_row("SELECT id FROM pelanggan WHERE mikrotik_secrets_id = ? AND id != ?", [$secretId, $id]);
        if ($usedBy) {
            flash('danger', 'Secret PPP ini sudah dipakai pelanggan lain.');
            redirect($back_url);
        }

        $rules = [
            'nama'    => ['label' => 'Nama', 'required' => true, 'max' => 150],
            'no_hp'   => ['label' => 'No HP', 'max' => 20],
            'alamat'  => ['label' => 'Alamat', 'max' => 500],
        ];
        $errs = validate_input($rules, $_POST);
        if ($errs) { flash('danger', implode(' ', $errs)); redirect($back_url); }

        $oldRow   = db_row("SELECT status FROM pelanggan WHERE id = ?", [$id]);
        $newStatus = in_array(post('status'), ['aktif','nonaktif','isolir']) ? post('status') : 'aktif';

        // paket_id tidak diubah lewat form edit ini — upgrade/downgrade
        // paket cuma lewat halaman Status Pelanggan (satu pintu).
        $data = [
            'mikrotik_secrets_id' => $secretId,
            'nama'            => trim(post('nama')),
            'no_hp'           => trim(post('no_hp')),
            'alamat'          => trim(post('alamat')),
            'area_id'         => post('area_id') ?: null,
            'status'          => $newStatus,
            'keterangan'      => mb_substr(trim(post('keterangan')), 0, 500),
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        db_update('pelanggan', $data, 'id = ?', [$id]);

        if ($oldRow && $oldRow['status'] !== $newStatus) {
            db_insert('pelanggan_status_log', [
                'pelanggan_id' => $id,
                'tipe'         => 'status',
                'details'      => json_encode(['lama' => $oldRow['status'], 'baru' => $newStatus]),
                'diubah_oleh'  => current_user()['id'],
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        flash('success', 'Data pelanggan berhasil diperbarui.');
        redirect($back_url);

    // ── DELETE ────────────────────────────────────────────────
    case 'delete':
        auth_role([ROLE_ADMIN]);
        $id = (int)get('id');
        if (!$id) { flash('danger', 'ID tidak valid.'); redirect($back_url); }

        $count = db_row("SELECT COUNT(*) as n FROM pembayaran WHERE pelanggan_id = ?", [$id])['n'];
        if ($count > 0) {
            flash('danger', 'Pelanggan ini memiliki ' . $count . ' data pembayaran. Hapus pembayaran terlebih dahulu.');
            redirect($back_url);
        }

        db_delete('pelanggan', 'id = ?', [$id]);
        flash('success', 'Pelanggan berhasil dihapus.');
        redirect($back_url);

    default:
        redirect($back_url);
}
