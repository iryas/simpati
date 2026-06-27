<?php
// ============================================================
//  KAHFINET - Modul Paket (Action Handler)
//  Paket adalah data detail/bisnis yang menempel (1:1) ke satu
//  PPP Profile yang sudah disync dari Mikrotik (mikrotik_profiles_cache).
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/paket/views.php';

switch ($action) {

    // ── DATATABLE (server-side) ───────────────────────────────
    case 'datatable':
        $draw   = (int)get('draw', 1);
        $start  = max(0, (int)get('start', 0));
        $length = (int)get('length', 10);
        $length = $length > 0 ? min($length, 100) : 10;
        $search = trim($_GET['search']['value'] ?? '');

        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (nama LIKE ? OR keterangan LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $orderCols = [1 => 'nama', 2 => 'kecepatan', 3 => 'harga', 5 => 'status'];
        $orderCol  = (int)($_GET['order'][0]['column'] ?? 3);
        $orderDir  = strtolower($_GET['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $orderBy   = $orderCols[$orderCol] ?? 'harga';

        $recordsTotal = (int)db_row("SELECT COUNT(*) as n FROM paket")['n'];

        $recordsFiltered = (int)db_row(
            "SELECT COUNT(*) as n FROM paket WHERE $where",
            $params
        )['n'];

        $rows = db_rows(
            "SELECT * FROM paket WHERE $where ORDER BY $orderBy $orderDir LIMIT $length OFFSET $start",
            $params
        );

        $isAdmin = current_user()['role'] === ROLE_ADMIN;
        $data = [];

        foreach ($rows as $i => $p) {
            $aksi = '';
            if ($isAdmin) {
                $aksi .= '<button class="btn btn-warning btn-xs btn-edit-paket" data-id="' . (int)$p['id'] . '" data-toggle="modal" data-target="#modalEdit"><i class="fas fa-edit"></i></button> ';
                $aksi .= '<a href="' . BASE_URL . 'modules/paket/act.php?action=delete&id=' . (int)$p['id'] . '" class="btn btn-danger btn-xs btn-hapus" data-label="paket ' . clean($p['nama']) . '"><i class="fas fa-trash"></i></a>';
            }

            $data[] = [
                'no'          => $start + $i + 1,
                'nama'        => '<span class="font-weight-bold">' . clean($p['nama']) . '</span>',
                'kecepatan'   => (int)$p['kecepatan'] . ' Mbps',
                'harga'       => '<span class="text-success font-weight-bold">' . rupiah((int)$p['harga']) . '</span>',
                'keterangan'  => clean($p['keterangan'] ?? '—'),
                'status'      => badge_status($p['status']),
                'aksi'        => $aksi,
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

    case 'get_json':
        $row = db_row("SELECT * FROM paket WHERE id = ?", [(int)get('id')]);
        if (!$row) json_res(false, 'Tidak ditemukan.');
        json_res(true, '', $row);

    case 'create':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }

        $profileId = (int)post('mikrotik_profiles_id');
        if (!$profileId || !db_row("SELECT id FROM mikrotik_profiles_cache WHERE id = ?", [$profileId])) {
            flash('danger', 'Profile PPP wajib dipilih dan harus valid. Sync dulu dari menu Mikrotik > Profile kalau belum ada datanya.');
            redirect($back_url);
        }
        $usedBy = db_row("SELECT id FROM paket WHERE mikrotik_profiles_id = ?", [$profileId]);
        if ($usedBy) {
            flash('danger', 'Profile PPP ini sudah dipakai paket lain.');
            redirect($back_url);
        }

        $data = [
            'mikrotik_profiles_id' => $profileId,
            'nama'        => trim(post('nama')),
            'kecepatan'   => (int)post('kecepatan'),
            'harga'       => (int)post('harga'),
            'status'      => post('status', 'aktif'),
            'keterangan'  => trim(post('keterangan')),
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        if (empty($data['nama'])) { flash('danger', 'Nama paket wajib diisi.'); redirect($back_url); }

        db_insert('paket', $data);
        flash('success', 'Paket berhasil ditambahkan.');
        redirect($back_url);

    case 'update':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }
        $id = (int)post('id');

        $profileId = (int)post('mikrotik_profiles_id');
        if (!$profileId || !db_row("SELECT id FROM mikrotik_profiles_cache WHERE id = ?", [$profileId])) {
            flash('danger', 'Profile PPP wajib dipilih dan harus valid. Sync dulu dari menu Mikrotik > Profile kalau belum ada datanya.');
            redirect($back_url);
        }
        $usedBy = db_row("SELECT id FROM paket WHERE mikrotik_profiles_id = ? AND id != ?", [$profileId, $id]);
        if ($usedBy) {
            flash('danger', 'Profile PPP ini sudah dipakai paket lain.');
            redirect($back_url);
        }

        $data = [
            'mikrotik_profiles_id' => $profileId,
            'nama'        => trim(post('nama')),
            'kecepatan'   => (int)post('kecepatan'),
            'harga'       => (int)post('harga'),
            'status'      => post('status', 'aktif'),
            'keterangan'  => trim(post('keterangan')),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        db_update('paket', $data, 'id = ?', [$id]);
        flash('success', 'Paket berhasil diperbarui.');
        redirect($back_url);

    case 'delete':
        auth_role([ROLE_ADMIN]);
        $id    = (int)get('id');
        $count = db_row("SELECT COUNT(*) as n FROM pelanggan WHERE paket_id = ?", [$id])['n'];
        if ($count > 0) {
            flash('danger', 'Paket ini masih digunakan oleh ' . $count . ' pelanggan.');
            redirect($back_url);
        }

        db_delete('paket', 'id = ?', [$id]);
        flash('success', 'Paket berhasil dihapus.');
        redirect($back_url);

    default:
        redirect($back_url);
}
