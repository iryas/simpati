<?php
// ============================================================
//  MONITORING · Modul Offline — Endpoint data live (JSON)
//  Mengembalikan jumlah ONU offline, area terdampak, dan
//  timestamp referensi cache untuk update UI tanpa reload.
// ============================================================
require_once __DIR__ . '/../../_data.php';
header('Content-Type: application/json; charset=utf-8');

$data = mon_offline_by_area();

// Cari durasi terlama.
$maxDur = 0;
foreach ($data['areas'] as $list) {
    foreach ($list as $onu) {
        if (($onu['durasi_detik'] ?? 0) > $maxDur) $maxDur = $onu['durasi_detik'];
    }
}

// Helper durasi inline.
$fmt = function (?int $s): string {
    if (!$s || $s <= 0) return '—';
    if ($s < 60)    return $s . 'd';
    if ($s < 3600)  return floor($s / 60) . 'mnt';
    if ($s < 86400) return floor($s / 3600) . 'j ' . floor(($s % 3600) / 60) . 'mnt';
    return floor($s / 86400) . 'hr ' . floor(($s % 86400) / 3600) . 'j';
};

echo json_encode([
    'ok'          => true,
    'ts'          => date('c'),
    'ref_ts'      => $data['ref'] ? date('d M Y, H:i:s', $data['ref']) : '—',
    'total'       => $data['total'],
    'area_count'  => count($data['areas']),
    'max_durasi'  => $fmt($maxDur),
], JSON_UNESCAPED_UNICODE);
