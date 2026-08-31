<?php
// ============================================================
//  KAHFINET - Modul Laporan (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KEUANGAN]);

$action = get('action');

switch ($action) {

    case 'export_csv':
        $bulan    = get('bulan', date('Y-m'));
        $tipe     = get('tipe');
        $paket_id = (int)get('paket_id', 0);
        $metode   = get('metode', '');
        $q        = trim(get('q', ''));

        // Filter pakai bulan_tagihan (periode tagihan) — SAMA seperti tabel
        // "Detail Tagihan" di halaman Laporan. Sebelumnya pakai tgl_bayar
        // (tanggal bayar aktual), jadi CSV bisa beda isi dari tabel yang
        // sedang dilihat admin untuk bulan yang sama (transaksi telat bayar
        // lintas bulan). CSV kini juga ikut menghormati filter Tipe/Kategori
        // Paket/Metode Pembayaran yang sedang dipakai di halaman, supaya
        // yang diunduh persis sama dengan yang sedang dilihat admin.
        $where  = 'py.bulan_tagihan = ?';
        $params = [$bulan];

        if ($tipe === 'tanpa_potongan') {
            $where .= " AND py.status='lunas' AND py.potongan = 0";
        } elseif ($tipe === 'dengan_potongan') {
            $where .= " AND py.status='lunas' AND py.potongan > 0";
        } elseif ($tipe === 'tunggakan') {
            $where .= " AND py.status='belum'";
        } else {
            // Default (Tipe tidak dipilih): tetap fokus ke pendapatan yang
            // sudah diterima, sama seperti perilaku Export CSV sebelumnya.
            $where .= " AND py.status='lunas'";
        }

        if ($paket_id > 0) {
            $where .= ' AND py.paket_id = ?';
            $params[] = $paket_id;
        }
        if (in_array($metode, ['tunai', 'transfer'], true)) {
            $where .= ' AND py.metode = ?';
            $params[] = $metode;
        }
        if ($q !== '') {
            $where .= ' AND (pl.nama LIKE ? OR pl.no_hp LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        $rows  = db_rows(
            "SELECT pl.nama as pelanggan, pl.no_hp, pk.nama as paket,
                    py.jumlah, py.potongan, py.terbayar, py.metode,
                    py.tgl_bayar, py.status, py.keterangan
             FROM pembayaran py
             LEFT JOIN pelanggan pl ON pl.id = py.pelanggan_id
             LEFT JOIN paket pk ON pk.id = py.paket_id
             WHERE $where
             ORDER BY py.tgl_bayar DESC",
            $params
        );

        $filename = 'laporan_' . $bulan . '_' . date('His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // BOM untuk Excel
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Pelanggan', 'No HP', 'Paket', 'Jumlah Tagihan', 'Potongan (hari)', 'Terbayar', 'Metode', 'Tgl Bayar', 'Status', 'Keterangan']);
        foreach ($rows as $r) {
            // Kolom "Terbayar" = uang yg beneran diterima (sudah dikurangi
            // potongan). Sebelumnya CSV cuma export "Jumlah" (tagihan kotor),
            // jadi total pendapatan di CSV lebih besar dari kenyataan kalau
            // ada transaksi kena potongan.
            fputcsv($out, [
                $r['pelanggan'],
                $r['no_hp'],
                $r['paket'],
                $r['jumlah'],
                $r['potongan'],
                $r['terbayar'],
                ($r['metode'] ?? 'tunai') === 'transfer' ? 'Transfer' : 'Tunai',
                $r['tgl_bayar'],
                $r['status'],
                $r['keterangan'],
            ]);
        }
        fclose($out);
        exit;

    // ── DATATABLE (server-side) — tabel Detail Tagihan di Laporan Pendapatan ──
    case 'datatable_detail':
        $draw   = (int)get('draw', 1);
        $start  = max(0, (int)get('start', 0));
        $length = (int)get('length', 15);
        $length = $length > 0 ? min($length, 100) : 15;

        $bulan    = get('bulan', date('Y-m'));
        $tipe     = get('tipe');
        $paket_id = (int)get('paket_id', 0);
        $metode   = get('metode', '');
        $q        = trim(get('q', ''));

        // Filter dasar (tanpa kotak cari DataTable) — SAMA persis dengan
        // logika filter Tahun/Bulan/Kategori Paket/Tipe/Metode di halaman.
        $where  = 'py.bulan_tagihan = ?';
        $params = [$bulan];

        if ($tipe === 'tanpa_potongan') {
            $where .= " AND py.status='lunas' AND py.potongan = 0";
        } elseif ($tipe === 'dengan_potongan') {
            $where .= " AND py.status='lunas' AND py.potongan > 0";
        } elseif ($tipe === 'tunggakan') {
            $where .= " AND py.status='belum'";
        }
        if ($paket_id > 0) {
            $where .= ' AND py.paket_id = ?';
            $params[] = $paket_id;
        }
        if (in_array($metode, ['tunai', 'transfer'], true)) {
            $where .= ' AND py.metode = ?';
            $params[] = $metode;
        }

        $recordsTotal = (int)db_row(
            "SELECT COUNT(*) n FROM pembayaran py WHERE $where",
            $params
        )['n'];

        // Filter "Cari Pelanggan" dari form (nama atau no HP) — sama seperti
        // pola pencarian di modul Pembayaran, bukan kotak cari bawaan DataTable.
        $whereFiltered  = $where;
        $paramsFiltered = $params;
        if ($q !== '') {
            $whereFiltered   .= ' AND (pl.nama LIKE ? OR pl.no_hp LIKE ?)';
            $paramsFiltered[] = "%$q%";
            $paramsFiltered[] = "%$q%";
        }

        $recordsFiltered = (int)db_row(
            "SELECT COUNT(*) n FROM pembayaran py
             LEFT JOIN pelanggan pl ON pl.id = py.pelanggan_id
             WHERE $whereFiltered",
            $paramsFiltered
        )['n'];

        $orderCols = [1 => 'pl.nama', 3 => 'py.jumlah', 5 => 'py.terbayar', 7 => 'py.tgl_bayar'];
        $orderCol  = (int)($_GET['order'][0]['column'] ?? 7);
        $orderDir  = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $orderBy   = $orderCols[$orderCol] ?? 'py.tgl_bayar';

        $rows = db_rows(
            "SELECT py.*, pl.nama as nama_pelanggan, pk.nama as nama_paket
             FROM pembayaran py
             LEFT JOIN pelanggan pl ON pl.id = py.pelanggan_id
             LEFT JOIN paket pk ON pk.id = py.paket_id
             WHERE $whereFiltered
             ORDER BY $orderBy $orderDir, py.id DESC
             LIMIT $length OFFSET $start",
            $paramsFiltered
        );

        $data = [];
        foreach ($rows as $i => $r) {
            if ($r['status'] === 'belum') {
                $tipeCell = '<span class="badge badge-danger">Tunggakan</span>';
            } elseif ((int)$r['potongan'] > 0) {
                $tipeCell = '<span class="badge badge-warning">Dengan Potongan</span>';
            } else {
                $tipeCell = '<span class="badge badge-success">Tanpa Potongan</span>';
            }

            $potonganCell = '<span class="text-muted">—</span>';
            if ($r['status'] === 'lunas' && (int)$r['potongan'] > 0) {
                $potonganCell = (int)$r['potongan'] . ' hari' .
                    '<br><small class="text-danger">- ' . rupiah((int)$r['jumlah'] - (int)$r['terbayar']) . '</small>';
            }

            $metodeCell = '<span class="text-muted">—</span>';
            if ($r['status'] === 'lunas') {
                $metodeCell = ($r['metode'] ?? 'tunai') === 'transfer'
                    ? '<span class="badge badge-info">Transfer</span>'
                    : '<span class="badge badge-secondary">Tunai</span>';
            }

            $data[] = [
                'no'        => $start + $i + 1,
                'pelanggan' => clean($r['nama_pelanggan'] ?? '—'),
                'paket'     => clean($r['nama_paket'] ?? '—'),
                'jumlah'    => rupiah((int)$r['jumlah']),
                'potongan'  => $potonganCell,
                'terbayar'  => $r['status'] === 'lunas' ? rupiah((int)$r['terbayar']) : '<span class="text-muted">—</span>',
                'metode'    => $metodeCell,
                'tgl_bayar' => $r['tgl_bayar'] ? tgl_indo($r['tgl_bayar']) : '<span class="text-muted">—</span>',
                'tipe'      => $tipeCell,
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        redirect(BASE_URL . 'modules/laporan/views.php');
}
