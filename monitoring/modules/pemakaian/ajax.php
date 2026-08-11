<?php
// ============================================================
//  MONITORING · Modul Pemakaian — Endpoint data live (JSON)
//  Ringkasan pemakaian bandwidth per bulan (untuk update UI).
// ============================================================
require_once __DIR__ . '/../../_data.php';
header('Content-Type: application/json; charset=utf-8');

$bulan = isset($_GET['bulan']) ? preg_replace('/[^0-9\-]/', '', (string)$_GET['bulan']) : '';
$data  = mon_pemakaian_data($bulan);
$top   = $data['rows'][0] ?? null;

echo json_encode([
    'ok'          => true,
    'ts'          => date('c'),
    'bulan'       => $data['bulan'],
    'count'       => $data['count'],
    'total_bytes' => $data['total_bytes'],
    'total_fmt'   => mon_fmt_bytes($data['total_bytes']),
    'avg_fmt'     => mon_fmt_bytes($data['avg_bytes']),
    'area_count'  => count($data['areas']),
    'top'         => $top ? ['pelanggan' => $top['pelanggan'], 'bytes' => mon_fmt_bytes((int)$top['bytes_out']), 'area' => $top['area']] : null,
], JSON_UNESCAPED_UNICODE);
