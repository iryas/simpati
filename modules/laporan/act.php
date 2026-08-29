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

    default:
        redirect(BASE_URL . 'modules/laporan/views.php');
}
