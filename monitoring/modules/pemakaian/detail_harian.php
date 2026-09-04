<?php
// ============================================================
//  MONITORING · Modul Pemakaian — Detail Pemakaian Harian (JSON)
//  Dipanggil dari modal "Detail" di tabel Peringkat Pemakai.
// ============================================================
require_once __DIR__ . '/../../_data.php';
header('Content-Type: application/json; charset=utf-8');

$pelanggan_id = (int)($_GET['pelanggan_id'] ?? 0);
$bulan        = isset($_GET['bulan']) ? preg_replace('/[^0-9\-]/', '', (string)$_GET['bulan']) : '';
if (!$bulan) $bulan = bulan_tagihan_sekarang();

if ($pelanggan_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Pelanggan tidak valid'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = mon_pemakaian_harian($pelanggan_id, $bulan);
if (!$data['ok']) {
    http_response_code(404);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$hari = array_map(function ($h) {
    return [
        'tanggal'     => $h['tanggal'],
        'tanggal_fmt' => tgl_indo($h['tanggal']),
        'ada'         => $h['bytes_out'] !== null,
        'bytes_fmt'   => $h['bytes_out'] !== null ? mon_fmt_bytes($h['bytes_out']) : '—',
        'jam_fmt'     => $h['uptime_seconds'] !== null ? mon_fmt_durasi($h['uptime_seconds']) : '—',
        'hari_ini'    => $h['hari_ini'],
    ];
}, $data['hari']);

echo json_encode([
    'ok'                 => true,
    'pelanggan'          => $data['pelanggan'],
    'area'               => $data['area'],
    'periode_label'      => $data['periode_label'],
    'mulai_tercatat'     => $data['mulai_tercatat'],
    'mulai_tercatat_fmt' => $data['mulai_tercatat'] ? tgl_indo($data['mulai_tercatat']) : null,
    'hari'               => $hari,
], JSON_UNESCAPED_UNICODE);
