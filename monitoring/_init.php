<?php
// ============================================================
//  MONITORING JARINGAN — Init & Guard
//  Reuse sesi staf. Akses hanya Admin & Teknisi.
// ============================================================
require_once __DIR__ . '/../system/init.php';

// Script CLI (worker.php acs:sync, dll) tidak punya sesi HTTP — akses shell
// server sudah inherently privileged, jadi guard login khusus untuk web.
if (PHP_SAPI !== 'cli') {
    auth_check();                              // wajib login staf
    auth_role([ROLE_ADMIN, ROLE_TEKNISI]);     // hanya admin & teknisi
}

if (!defined('MONITORING_URL')) {
    define('MONITORING_URL', BASE_URL . 'monitoring/');
}
