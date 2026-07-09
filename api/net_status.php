<?php
// ============================================================
//  KAHFINET - API: Status Koneksi Mikrotik & ACS (AJAX)
// ============================================================
require_once __DIR__ . '/../system/init.php';
auth_check();

if (!in_array(current_user()['role'], [ROLE_ADMIN, ROLE_TEKNISI])) {
    json_res(false, 'Akses ditolak.');
}

$mikrotik_ok = mikrotik_is_online();
$acs_res     = acs_test_connection();

json_res(true, '', [
    'mikrotik' => $mikrotik_ok,
    'acs'      => $acs_res['ok'],
]);
