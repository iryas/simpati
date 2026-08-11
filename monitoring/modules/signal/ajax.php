<?php
// ============================================================
//  MONITORING · Modul Signal — Endpoint data live (JSON)
//  Mengembalikan hitungan per kategori sinyal untuk update
//  chip/stats UI tanpa reload penuh. Hanya kritis+waspada.
// ============================================================
require_once __DIR__ . '/../../_data.php';
header('Content-Type: application/json; charset=utf-8');

$data = mon_signal_list();

echo json_encode([
    'ok'        => true,
    'ts'        => date('c'),
    'ref_ts'    => $data['ref'] ? date('d M Y, H:i:s', $data['ref']) : '—',
    'total'     => $data['total'],
    'total_all' => $data['total_all'],
    'counts'    => $data['counts'],
], JSON_UNESCAPED_UNICODE);
