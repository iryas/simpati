<?php
// ============================================================
//  MONITORING · Modul ONU — Endpoint data live (JSON)
// ============================================================
require_once __DIR__ . '/../../_data.php';
header('Content-Type: application/json; charset=utf-8');

// Nilai WiFi saat ini untuk 1 perangkat (prefill modal Aksi) — read-only.
if (isset($_GET['wifi'])) {
    $d = mon_onu_detail((string)$_GET['wifi']);
    echo json_encode([
        'ok'   => (bool)$d,
        'ssid' => $d['wifi_ssid'] ?? null,
        'pass' => $d['wifi_pass'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$list  = mon_onu_list();
$count = ['all' => count($list), 'online' => 0, 'offline' => 0, 'isolir' => 0, 'unmapped' => 0];
foreach ($list as $r) {
    if (!$r['mapped']) { $count['unmapped']++; }
    else { $count[$r['status']]++; }
}

$rows = array_map(function ($r) {
    return [
        'device_id'   => $r['device_id'],
        'pelanggan'   => $r['pelanggan'],
        'area'        => $r['area'],
        'model'       => $r['model'],
        'status'      => $r['status'],
        'mapped'      => $r['mapped'],
        'rx'          => $r['rx'],
        'last_inform' => $r['last_inform'],
    ];
}, $list);

echo json_encode([
    'ok'     => true,
    'ts'     => date('c'),
    'counts' => $count,
    'rows'   => $rows,
], JSON_UNESCAPED_UNICODE);
