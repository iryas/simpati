<?php
// ============================================================
//  API — Pencarian Pelanggan untuk Select2 AJAX
//  GET ?q=nama&status=aktif&limit=5
// ============================================================
require_once __DIR__ . '/../system/init.php';
auth_check();

$q      = trim(get('q', ''));
$status = get('status', 'aktif');   // aktif | semua
$limit  = max(1, min(50, (int)get('limit', 5)));

$where  = '1=1';
$params = [];

// Filter status
if ($status !== 'semua') {
    $where   .= ' AND pl.status = ?';
    $params[] = $status;
}

// Filter pencarian
if ($q !== '') {
    $where   .= ' AND (pl.nama LIKE ? OR pl.no_hp LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$rows = db_rows(
    "SELECT pl.id, pl.nama, pl.no_hp, pl.status, pl.paket_id, pk.harga
     FROM pelanggan pl
     LEFT JOIN paket pk ON pk.id = pl.paket_id
     WHERE $where
     ORDER BY pl.nama ASC
     LIMIT $limit",
    $params
);

$results = array_map(function ($r) {
    $label = $r['nama'];
    if ($r['no_hp']) $label .= ' (' . $r['no_hp'] . ')';
    return [
        'id'       => $r['id'],
        'text'     => $label,
        'no_hp'    => $r['no_hp'] ?? '',
        'status'   => $r['status'],
        'paket_id' => $r['paket_id'],
        'harga'    => (int)$r['harga'],
    ];
}, $rows);

header('Content-Type: application/json');
echo json_encode(['results' => $results]);
