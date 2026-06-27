<?php
// ============================================================
//  KAHFINET - Modul Laporan (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KASIR]);

$action = get('action');

switch ($action) {

    case 'export_csv':
        $bulan = get('bulan', date('Y-m'));
        $rows  = db_rows(
            "SELECT pl.nama as pelanggan, pl.no_hp, pk.nama as paket,
                    py.jumlah, py.tgl_bayar, py.status, py.keterangan
             FROM pembayaran py
             LEFT JOIN pelanggan pl ON pl.id = py.pelanggan_id
             LEFT JOIN paket pk ON pk.id = py.paket_id
             WHERE py.status='lunas' AND DATE_FORMAT(py.tgl_bayar,'%Y-%m') = ?
             ORDER BY py.tgl_bayar DESC",
            [$bulan]
        );

        $filename = 'laporan_' . $bulan . '_' . date('His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // BOM untuk Excel
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Pelanggan', 'No HP', 'Paket', 'Jumlah', 'Tgl Bayar', 'Status', 'Keterangan']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['pelanggan'],
                $r['no_hp'],
                $r['paket'],
                $r['jumlah'],
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
