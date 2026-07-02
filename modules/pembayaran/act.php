<?php
// ============================================================
//  KAHFINET - Modul Pembayaran (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KASIR]);

$action = get('action') ?: post('action');

// Pertahankan filter (bulan/search/status) yang sedang aktif di halaman,
// supaya setelah create/bayar/edit/hapus, user balik ke tampilan yang sama.
$retBulan  = post('ret_bulan') ?: get('ret_bulan');
$retSearch = post('ret_search') ?: get('ret_search');
$retStatus = post('ret_status') ?: get('ret_status');

$backParams = [];
if ($retBulan !== '')  $backParams['bulan']  = $retBulan;
if ($retSearch !== '') $backParams['search'] = $retSearch;
if ($retStatus !== '') $backParams['status'] = $retStatus;

$back_url = BASE_URL . 'modules/pembayaran/views.php' . ($backParams ? '?' . http_build_query($backParams) : '');

switch ($action) {

    // ── DATATABLE (server-side) ───────────────────────────────
    case 'datatable':
        $draw   = (int)get('draw', 1);
        $start  = max(0, (int)get('start', 0));
        $length = (int)get('length', 15);
        $length = $length > 0 ? min($length, 100) : 15;
        $search = trim(get('q'));
        $status = get('status_filter');
        $bulan  = get('bulan');

        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $where   .= ' AND (pl.nama LIKE ? OR pl.no_hp LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($status) {
            $where   .= ' AND py.status = ?';
            $params[] = $status;
        }
        if ($bulan) {
            $where   .= ' AND py.bulan_tagihan = ?';
            $params[] = $bulan;
        }

        $orderCols = [3 => 'py.jumlah', 4 => 'py.terbayar', 5 => 'py.potongan', 6 => 'py.tgl_bayar', 8 => 'py.status'];
        $orderCol  = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir  = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $orderBy   = $orderCols[$orderCol] ?? 'py.id';

        $recordsTotal = (int)db_row(
            "SELECT COUNT(*) as n FROM pembayaran py LEFT JOIN pelanggan pl ON pl.id = py.pelanggan_id WHERE " .
                ($bulan ? 'py.bulan_tagihan = ?' : '1=1'),
            $bulan ? [$bulan] : []
        )['n'];

        $recordsFiltered = (int)db_row(
            "SELECT COUNT(*) as n FROM pembayaran py
             LEFT JOIN pelanggan pl ON pl.id = py.pelanggan_id
             WHERE $where",
            $params
        )['n'];

        $rows = db_rows(
            "SELECT py.*, pl.nama as nama_pelanggan, pl.no_hp, u.nama as nama_kasir
             FROM pembayaran py
             LEFT JOIN pelanggan pl ON pl.id = py.pelanggan_id
             LEFT JOIN pengguna u ON u.id = py.kasir_id
             WHERE $where
             ORDER BY $orderBy $orderDir
             LIMIT $length OFFSET $start",
            $params
        );

        $isAdmin = current_user()['role'] === ROLE_ADMIN;
        $data = [];

        foreach ($rows as $i => $r) {
            $pelangganCell = '<div class="font-weight-bold" style="font-size:13px">' . clean($r['nama_pelanggan']) . '</div>' .
                '<small class="text-muted">' . clean($r['no_hp'] ?? '') . '</small>';

            $terbayarCell = '<span class="text-muted">—</span>';
            if ($r['status'] === 'lunas') {
                $warnaCell    = (int)$r['potongan'] > 0 ? 'text-danger' : 'text-success';
                $terbayarCell = '<span class="font-weight-bold ' . $warnaCell . '">' . rupiah((int)$r['terbayar']) . '</span>';
            }

            $aksi = '';
            if ($r['status'] === 'belum') {
                $aksi .= '<button class="btn btn-success btn-xs btn-bayar" data-id="' . (int)$r['id'] . '" data-jumlah="' . (int)$r['jumlah'] . '" data-nama="' . clean($r['nama_pelanggan']) . '" data-potongan="' . (int)$r['potongan'] . '" data-toggle="modal" data-target="#modalBayar" title="Bayar"><i class="fas fa-money-bill mr-1"></i>Bayar</button> ';
                $aksi .= '<button class="btn btn-outline-secondary btn-xs btn-atur-potongan" data-id="' . (int)$r['id'] . '" data-jumlah="' . (int)$r['jumlah'] . '" data-nama="' . clean($r['nama_pelanggan']) . '" data-potongan="' . (int)$r['potongan'] . '" data-toggle="modal" data-target="#modalPotongan" title="Atur Potongan"><i class="fas fa-percent"></i></button> ';
            }
            if ($r['status'] === 'lunas' && $isAdmin) {
                $aksi .= '<button class="btn btn-warning btn-xs btn-edit-bayar" data-id="' . (int)$r['id'] . '" data-toggle="modal" data-target="#modalEditBayar" title="Edit Pembayaran"><i class="fas fa-edit"></i></button> ';
            }
            if ($isAdmin) {
                $deleteUrl = BASE_URL . 'modules/pembayaran/act.php?action=delete&id=' . (int)$r['id'] .
                    '&ret_bulan=' . urlencode($bulan) . '&ret_search=' . urlencode($search) . '&ret_status=' . urlencode($status);
                $aksi .= '<a href="' . $deleteUrl . '" class="btn btn-danger btn-xs btn-hapus" data-label="tagihan ini"><i class="fas fa-trash"></i></a>';
            }

            $potonganCell = '<span class="text-muted">—</span>';
            if ($r['status'] === 'lunas' && (int)$r['potongan'] > 0) {
                $potonganCell = (int)$r['potongan'] . ' hari' .
                    '<br><small class="text-danger">- ' . rupiah((int)$r['jumlah'] - (int)$r['terbayar']) . '</small>';
            } elseif ($r['status'] === 'belum' && (int)$r['potongan'] > 0) {
                $haPotongan = (int)$r['jumlah'] / 30;
                $nominalPotongan = (int)round($haPotongan * (int)$r['potongan']);
                $potonganCell = (int)$r['potongan'] . ' hari <span class="badge badge-secondary">terjadwal</span>' .
                    '<br><small class="text-danger">- ' . rupiah($nominalPotongan) . '</small>';
            }

            $checkboxCell = $r['status'] === 'belum'
                ? '<input type="checkbox" class="chk-bayar-massal" value="' . (int)$r['id'] . '">'
                : '';

            $data[] = [
                'no'        => $start + $i + 1,
                'checkbox'  => $checkboxCell,
                'pelanggan' => $pelangganCell,
                'jumlah'    => '<span class="font-weight-bold">' . rupiah((int)$r['jumlah']) . '</span>',
                'terbayar'  => $terbayarCell,
                'potongan'  => $potonganCell,
                'tgl_bayar' => $r['tgl_bayar'] ? tgl_indo($r['tgl_bayar']) : '<span class="text-muted">—</span>',
                'kasir'     => clean($r['nama_kasir'] ?? '—'),
                'status'    => badge_status($r['status'], $r['bulan_tagihan']),
                'aksi'      => $aksi,
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
        $row = db_row("SELECT * FROM pembayaran WHERE id = ?", [(int)get('id')]);
        if (!$row) json_res(false, 'Tidak ditemukan.');
        json_res(true, '', $row);

    case 'create':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }
        $pelanggan_id = (int)post('pelanggan_id');
        $pl = db_row("SELECT * FROM pelanggan WHERE id = ?", [$pelanggan_id]);
        if (!$pl) {
            flash('danger', 'Pelanggan tidak ditemukan.');
            redirect($back_url);
        }

        $status = post('status', 'lunas');

        $petugasId = null;
        if ($status === 'lunas') {
            $petugasId = (int)post('kasir_id') ?: current_user()['id'];
            $petugas   = db_row("SELECT id FROM pengguna WHERE id = ? AND role IN ('admin','kasir')", [$petugasId]);
            if (!$petugas) {
                flash('danger', 'Petugas/kasir tidak valid.');
                redirect($back_url);
            }
        }

        $jumlah   = (int)post('jumlah');
        $potongan = $status === 'lunas' ? max(0, min(30, (int)post('potongan', 0))) : 0;
        $ha       = $jumlah / 30;
        $terbayar = $status === 'lunas' ? (int)round($jumlah - ($ha * $potongan)) : 0;

        $bulan_tagihan = post('bulan_tagihan');
        $existing = db_row(
            "SELECT id FROM pembayaran WHERE pelanggan_id = ? AND bulan_tagihan = ?",
            [$pelanggan_id, $bulan_tagihan]
        );

        $data = [
            'pelanggan_id'  => $pelanggan_id,
            'paket_id'      => $pl['paket_id'],
            'kasir_id'      => $petugasId,
            'jumlah'        => $jumlah,
            'bulan_tagihan' => $bulan_tagihan,
            'tgl_bayar'     => $status === 'lunas' ? (post('tgl_bayar') ?: date('Y-m-d')) : null,
            'status'        => $status,
            'potongan'      => $potongan,
            'terbayar'      => $terbayar,
            'keterangan'    => trim(post('keterangan')),
        ];

        if ($existing) {
            db_update('pembayaran', $data, 'id = ?', [$existing['id']]);
            flash('success', 'Tagihan untuk pelanggan & bulan ini sudah ada, data berhasil diperbarui.');
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            db_insert('pembayaran', $data);
            flash('success', 'Pembayaran berhasil dicatat.');
        }
        redirect($back_url);

        // ── GENERATE TAGIHAN MASSAL ────────────────────────────────
    case 'generate':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }
        $bulan = post('bulan') ?: date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $bulan = date('Y-m');
        }

        $pelanggans = db_rows(
            "SELECT pl.id, pl.paket_id, pk.harga
             FROM pelanggan pl
             JOIN paket pk ON pk.id = pl.paket_id
             WHERE pl.status = 'aktif'"
        );

        $created = 0;
        $skipped = 0;

        foreach ($pelanggans as $pl) {
            $exists = db_row(
                "SELECT id FROM pembayaran WHERE pelanggan_id = ? AND bulan_tagihan = ?",
                [$pl['id'], $bulan]
            );
            if ($exists) {
                $skipped++;
                continue;
            }

            db_insert('pembayaran', [
                'pelanggan_id'    => $pl['id'],
                'paket_id'        => $pl['paket_id'],
                'kasir_id'        => null,
                'jumlah'          => (int)$pl['harga'],
                'potongan'        => 0,
                'terbayar'        => 0,
                'bulan_tagihan'   => $bulan,
                'tgl_jatuh_tempo' => null,
                'tgl_bayar'       => null,
                'status'          => 'belum',
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            $created++;
        }

        $totalAktifTanpaPaket = db_row(
            "SELECT COUNT(*) as n FROM pelanggan WHERE status = 'aktif' AND paket_id IS NULL"
        )['n'];

        $msg = "$created tagihan berhasil dibuat untuk bulan " . date('M Y', strtotime($bulan . '-01')) . ".";
        if ($skipped > 0) $msg .= " $skipped pelanggan dilewati (sudah ada tagihan bulan ini).";
        if ($totalAktifTanpaPaket > 0) $msg .= " $totalAktifTanpaPaket pelanggan aktif dilewati (belum punya paket).";

        flash($created > 0 ? 'success' : 'warning', $msg);
        $backParams['bulan'] = $bulan;
        redirect(BASE_URL . 'modules/pembayaran/views.php?' . http_build_query($backParams));

        // ── ATUR POTONGAN (rencana, status tetap "belum") ──────────
    case 'set_potongan':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $id  = (int)post('id');
        $row = db_row("SELECT * FROM pembayaran WHERE id = ? AND status = 'belum'", [$id]);
        if (!$row) {
            flash('danger', 'Tagihan tidak ditemukan atau sudah lunas.');
            redirect($back_url);
        }

        $potongan = max(0, min(30, (int)post('potongan', 0)));
        db_update('pembayaran', ['potongan' => $potongan], 'id = ?', [$id]);

        flash('success', 'Potongan ' . $potongan . ' hari berhasil disimpan untuk tagihan ini.');
        redirect($back_url);

        // ── ATUR POTONGAN MASSAL (rencana, status tetap "belum") ───
    case 'set_potongan_massal':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $ids = post('ids');
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            flash('danger', 'Tidak ada tagihan yang dipilih.');
            redirect($back_url);
        }

        $potongan = max(0, min(30, (int)post('potongan', 0)));

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $diproses = db_query(
            "UPDATE pembayaran SET potongan = ? WHERE status = 'belum' AND id IN ($placeholders)",
            array_merge([$potongan], $ids)
        )->rowCount();

        $dilewati = count($ids) - $diproses;
        $msg = "Potongan $potongan hari berhasil diatur untuk $diproses tagihan.";
        if ($dilewati > 0) $msg .= " $dilewati tagihan dilewati (sudah lunas/tidak valid).";

        flash($diproses > 0 ? 'success' : 'warning', $msg);
        redirect($back_url);

        // ── BAYAR (dengan potongan) ────────────────────────────────
    case 'bayar':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $id  = (int)post('id');
        $row = db_row("SELECT * FROM pembayaran WHERE id = ?", [$id]);
        if (!$row) {
            flash('danger', 'Tagihan tidak ditemukan.');
            redirect($back_url);
        }

        $petugasId = (int)post('kasir_id');
        $petugas   = db_row("SELECT id FROM pengguna WHERE id = ? AND role IN ('admin','kasir')", [$petugasId]);
        if (!$petugas) {
            flash('danger', 'Petugas/kasir tidak valid.');
            redirect($back_url);
        }

        $potongan = (int)post('potongan', 0);
        $potongan = max(0, min(30, $potongan));

        $jumlah   = (int)$row['jumlah'];
        $ha       = $jumlah / 30;
        $terbayar = (int)round($jumlah - ($ha * $potongan));

        db_update('pembayaran', [
            'status'    => 'lunas',
            'tgl_bayar' => date('Y-m-d'),
            'kasir_id'  => $petugasId,
            'potongan'  => $potongan,
            'terbayar'  => $terbayar,
        ], 'id = ?', [$id]);

        flash('success', 'Pembayaran berhasil dikonfirmasi sebesar ' . rupiah($terbayar) . '.');
        redirect($back_url);

        // ── BAYAR MASSAL (banyak tagihan sekaligus, potongan sama) ──
    case 'bayar_massal':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $ids = post('ids');
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            flash('danger', 'Tidak ada tagihan yang dipilih.');
            redirect($back_url);
        }

        $petugasId = (int)post('kasir_id');
        $petugas   = db_row("SELECT id FROM pengguna WHERE id = ? AND role IN ('admin','kasir')", [$petugasId]);
        if (!$petugas) {
            flash('danger', 'Petugas/kasir tidak valid.');
            redirect($back_url);
        }

        // Potongan dipakai dari yang sudah diset duluan per baris (lewat
        // tombol "Atur Potongan"), bukan input bareng — kalau belum diset,
        // dianggap 0 (lunas penuh).
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_rows(
            "SELECT id, jumlah, potongan FROM pembayaran WHERE status = 'belum' AND id IN ($placeholders)",
            $ids
        );

        $diproses = 0;
        foreach ($rows as $row) {
            $jumlah   = (int)$row['jumlah'];
            $potongan = max(0, min(30, (int)$row['potongan']));
            $ha       = $jumlah / 30;
            $terbayar = (int)round($jumlah - ($ha * $potongan));

            db_update('pembayaran', [
                'status'    => 'lunas',
                'tgl_bayar' => date('Y-m-d'),
                'kasir_id'  => $petugasId,
                'potongan'  => $potongan,
                'terbayar'  => $terbayar,
            ], 'id = ?', [$row['id']]);
            $diproses++;
        }

        $dilewati = count($ids) - $diproses;
        $msg = "$diproses tagihan berhasil dibayar lunas.";
        if ($dilewati > 0) $msg .= " $dilewati tagihan dilewati (sudah lunas/tidak valid).";

        flash($diproses > 0 ? 'success' : 'warning', $msg);
        redirect($back_url);

        // ── EDIT (pembayaran yang sudah dikonfirmasi, admin saja) ──
    case 'update':
        auth_role([ROLE_ADMIN]);
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $id  = (int)post('id');
        $row = db_row("SELECT * FROM pembayaran WHERE id = ?", [$id]);
        if (!$row) {
            flash('danger', 'Tagihan tidak ditemukan.');
            redirect($back_url);
        }

        $petugasId = (int)post('kasir_id');
        $petugas   = db_row("SELECT id FROM pengguna WHERE id = ? AND role IN ('admin','kasir')", [$petugasId]);
        if (!$petugas) {
            flash('danger', 'Petugas/kasir tidak valid.');
            redirect($back_url);
        }

        $jumlah   = (int)post('jumlah');
        $potongan = max(0, min(30, (int)post('potongan', 0)));
        $ha       = $jumlah / 30;
        $terbayar = (int)round($jumlah - ($ha * $potongan));

        db_update('pembayaran', [
            'jumlah'     => $jumlah,
            'potongan'   => $potongan,
            'terbayar'   => $terbayar,
            'tgl_bayar'  => post('tgl_bayar') ?: $row['tgl_bayar'],
            'kasir_id'   => $petugasId,
            'keterangan' => trim(post('keterangan')),
        ], 'id = ?', [$id]);

        flash('success', 'Data pembayaran berhasil diperbarui.');
        redirect($back_url);

    case 'delete':
        auth_role([ROLE_ADMIN]);
        $id = (int)get('id');
        db_delete('pembayaran', 'id = ?', [$id]);
        flash('success', 'Data pembayaran berhasil dihapus.');
        redirect($back_url);

    default:
        redirect($back_url);
}
