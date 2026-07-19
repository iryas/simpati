<?php
// ============================================================
//  PORTAL PELANGGAN — Helper Data (billing & pemakaian)
//  Konvensi billing disamakan dengan modul admin.
// ============================================================
require_once __DIR__ . '/_auth.php';

// Nominal yang harus dibayar setelah potongan (dalam hari).
function portal_nominal(int $jumlah, int $potongan): int {
    return (int)round($jumlah - ($jumlah / 30 * $potongan));
}

// Label periode tagihan untuk bulan (Y-m).
function portal_periode(string $bulan_ym): string {
    $tgl_mulai = (int)app_setting('tgl_mulai_tagihan', '1');
    if ($tgl_mulai < 1) $tgl_mulai = 1;
    return label_periode_tagihan($bulan_ym, $tgl_mulai);
}

// Tagihan periode berjalan (baris pembayaran bulan ini), atau null.
function portal_tagihan_kini(int $pid): ?array {
    $r = db_row(
        "SELECT * FROM pembayaran
         WHERE pelanggan_id = ? AND bulan_tagihan = ?
         ORDER BY id DESC LIMIT 1",
        [$pid, bulan_tagihan_sekarang()]
    );
    return $r ?: null;
}

// Tunggakan: belum lunas untuk bulan-bulan lampau.
function portal_tunggakan(int $pid): array {
    $rows = db_rows(
        "SELECT * FROM pembayaran
         WHERE pelanggan_id = ? AND status = 'belum' AND bulan_tagihan < ?
         ORDER BY bulan_tagihan ASC",
        [$pid, bulan_tagihan_sekarang()]
    );
    $total = 0;
    $items = [];
    foreach ($rows as $r) {
        $n = portal_nominal((int)$r['jumlah'], (int)$r['potongan']);
        $total += $n;
        $r['nominal'] = $n;
        $items[] = $r;
    }
    return ['total' => $total, 'items' => $items, 'count' => count($items)];
}

// Riwayat pembayaran yang sudah lunas.
function portal_riwayat(int $pid, int $limit = 60): array {
    $limit = max(1, min(200, $limit));
    return db_rows(
        "SELECT py.*, pk.nama AS nama_paket
         FROM pembayaran py
         LEFT JOIN paket pk ON pk.id = py.paket_id
         WHERE py.pelanggan_id = ? AND py.status = 'lunas'
         ORDER BY py.tgl_bayar DESC, py.id DESC
         LIMIT " . (int)$limit,
        [$pid]
    );
}

// Pemakaian PPPoE untuk bulan (Y-m).
function portal_usage(int $pid, string $bulan): ?array {
    $r = db_row(
        "SELECT * FROM usage_pppoe WHERE pelanggan_id = ? AND bulan_tagihan = ? LIMIT 1",
        [$pid, $bulan]
    );
    return $r ?: null;
}

// Riwayat pemakaian beberapa bulan terakhir (untuk grafik).
function portal_usage_riwayat(int $pid, int $bulan = 6): array {
    $bulan = max(1, min(12, $bulan));
    return db_rows(
        "SELECT * FROM usage_pppoe WHERE pelanggan_id = ?
         ORDER BY bulan_tagihan DESC LIMIT " . (int)$bulan,
        [$pid]
    );
}

// Label ramah untuk kategori tiket.
function tiket_kategori_label(string $k): string {
    return [
        'koneksi_lambat' => 'Koneksi lambat',
        'tidak_konek'    => 'Tidak bisa konek',
        'perangkat'      => 'Perangkat/router',
        'tagihan'        => 'Tagihan',
        'lainnya'        => 'Lainnya',
    ][$k] ?? ucfirst($k);
}
