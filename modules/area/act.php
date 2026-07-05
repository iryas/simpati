<?php
// ============================================================
//  KAHFINET - Modul Data Area (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/area/views.php';

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

        $orderCols = [1 => 'nama'];
        $orderCol  = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir  = strtolower($_GET['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $orderBy   = $orderCols[$orderCol] ?? 'id';

        $recordsTotal = (int)db_row("SELECT COUNT(*) as n FROM area")['n'];

        $recordsFiltered = (int)db_row(
            "SELECT COUNT(*) as n FROM area WHERE $where",
            $params
        )['n'];

        $rows = db_rows(
            "SELECT a.*, COUNT(pl.id) as jumlah_pelanggan
             FROM area a
             LEFT JOIN pelanggan pl ON pl.area_id = a.id
             WHERE $where
             GROUP BY a.id
             ORDER BY $orderBy $orderDir
             LIMIT $length OFFSET $start",
            $params
        );

        $isAdmin = current_user()['role'] === ROLE_ADMIN;
        $data = [];

        foreach ($rows as $i => $a) {
            $aksi = '';
            if ($isAdmin) {
                $aksi .= '<button class="btn btn-warning btn-xs btn-edit-area" data-id="' . (int)$a['id'] . '" data-toggle="modal" data-target="#modalEdit"><i class="fas fa-edit"></i></button> ';
                $aksi .= '<a href="' . BASE_URL . 'modules/area/act.php?action=delete&id=' . (int)$a['id'] . '" class="btn btn-danger btn-xs btn-hapus" data-label="area ' . clean($a['nama']) . '"><i class="fas fa-trash"></i></a>';
            }

            $data[] = [
                'no'               => $start + $i + 1,
                'nama'             => '<span class="font-weight-bold">' . clean($a['nama']) . '</span>',
                'keterangan'       => clean($a['keterangan'] ?? '—'),
                'jumlah_pelanggan' => (int)$a['jumlah_pelanggan'],
                'aksi'             => $aksi,
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
        auth_role([ROLE_ADMIN]);
        $row = db_row("SELECT * FROM area WHERE id = ?", [(int)get('id')]);
        if (!$row) json_res(false, 'Tidak ditemukan.');
        json_res(true, '', $row);

    case 'create':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }

        $nama = trim(post('nama'));
        if (empty($nama)) { flash('danger', 'Nama area wajib diisi.'); redirect($back_url); }

        $dup = db_row("SELECT id FROM area WHERE nama = ?", [$nama]);
        if ($dup) { flash('danger', 'Nama area ini sudah ada.'); redirect($back_url); }

        db_insert('area', [
            'nama'       => $nama,
            'keterangan' => trim(post('keterangan')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        flash('success', 'Area berhasil ditambahkan.');
        redirect($back_url);

    case 'update':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }
        $id = (int)post('id');

        $nama = trim(post('nama'));
        if (empty($nama)) { flash('danger', 'Nama area wajib diisi.'); redirect($back_url); }

        $dup = db_row("SELECT id FROM area WHERE nama = ? AND id != ?", [$nama, $id]);
        if ($dup) { flash('danger', 'Nama area ini sudah ada.'); redirect($back_url); }

        db_update('area', [
            'nama'       => $nama,
            'keterangan' => trim(post('keterangan')),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        flash('success', 'Area berhasil diperbarui.');
        redirect($back_url);

    case 'delete':
        auth_role([ROLE_ADMIN]);
        $id = (int)get('id');
        db_delete('area', 'id = ?', [$id]);
        flash('success', 'Area berhasil dihapus.');
        redirect($back_url);

    default:
        redirect($back_url);
}
