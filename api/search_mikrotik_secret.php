<?php
// ============================================================
//  API — Pencarian Mikrotik Secret untuk Select2 AJAX
//  GET ?q=nama&pelanggan_id=0&limit=5
//  pelanggan_id: saat edit, secret milik pelanggan ini ikut ditampilkan
// ============================================================
require_once __DIR__ . '/../system/init.php';
auth_check();

$q            = trim(get('q', ''));
$pelanggan_id = (int)get('pelanggan_id', 0);
$limit        = max(1, min(50, (int)get('limit', 5)));

$params = [];

// Kondisi tampil: belum dipakai siapapun, ATAU milik pelanggan yg sedang diedit
$available = '(pl.id IS NULL OR pl.id = ?)';
$params[]  = $pelanggan_id ?: 0;

// Filter pencarian
$search = '';
if ($q !== '') {
    $search    = ' AND (msc.name LIKE ? OR msc.profile LIKE ?)';
    $like      = '%' . $q . '%';
    $params[]  = $like;
    $params[]  = $like;
}

$rows = db_rows(
    "SELECT msc.id, msc.name, msc.profile, pl.id as used_by_id, pl.nama as used_by_nama
     FROM mikrotik_secrets_cache msc
     LEFT JOIN pelanggan pl ON pl.mikrotik_secrets_id = msc.id
     WHERE $available $search
     ORDER BY msc.name ASC
     LIMIT $limit",
    $params
);

$results = array_map(function ($r) {
    $text = $r['name'];
    if ($r['profile']) $text .= ' — profile: ' . $r['profile'];
    return [
        'id'   => $r['id'],
        'text' => $text,
    ];
}, $rows);

header('Content-Type: application/json');
echo json_encode(['results' => $results]);
