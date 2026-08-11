<?php
// ============================================================
//  MONITORING · Modul Offline — Aksi / POST (JSON)
//  Diisi saat modul dibangun.
// ============================================================
require_once __DIR__ . '/../../_init.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
echo json_encode(['ok' => false, 'msg' => 'Belum ada aksi untuk modul ini.']);
