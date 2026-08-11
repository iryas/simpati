<?php
// ============================================================
//  MONITORING · Modul ONU — Aksi / POST (JSON)
//  set_wifi: ganti Nama (SSID) & Password WiFi via GenieACS
//  (TR-069 setParameterValues) untuk radio 2.4G + 5G sekaligus.
// ============================================================
require_once __DIR__ . '/../../_data.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

switch ($action) {

    case 'set_wifi':
        if (!csrf_verify()) {
            echo json_encode(['ok' => false, 'msg' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.']);
            exit;
        }

        $deviceId = trim((string)($_POST['device_id'] ?? ''));
        $ssid     = trim((string)($_POST['ssid'] ?? ''));
        $pass     = (string)($_POST['pass'] ?? '');
        $setPass  = $pass !== '';

        // ── Validasi ──
        if ($deviceId === '') {
            echo json_encode(['ok' => false, 'msg' => 'Perangkat tidak valid.']);
            exit;
        }
        if ($ssid === '' || mb_strlen($ssid) > 32) {
            echo json_encode(['ok' => false, 'msg' => 'Nama WiFi wajib diisi (maksimal 32 karakter).']);
            exit;
        }
        if ($setPass && (strlen($pass) < 8 || strlen($pass) > 63)) {
            echo json_encode(['ok' => false, 'msg' => 'Password WiFi harus 8–63 karakter (atau kosongkan bila tidak ingin mengubah).']);
            exit;
        }

        $dev = db_row(
            "SELECT g.device_id, g.pppoe_username, p.nama AS pelanggan
             FROM genieacs_devices_cache g
             LEFT JOIN mikrotik_secrets_cache s ON LOWER(s.name) = LOWER(g.pppoe_username)
             LEFT JOIN pelanggan p ON p.mikrotik_secrets_id = s.id
             WHERE g.device_id = ? LIMIT 1",
            [$deviceId]
        );
        if (!$dev) {
            echo json_encode(['ok' => false, 'msg' => 'Perangkat tidak ditemukan di cache.']);
            exit;
        }

        // ── Susun parameter untuk radio primary (2.4G .1 + 5G twin) ──
        $tgt       = mon_wifi_targets($deviceId);
        $instances = $tgt['instances'] ?: ['1'];

        $params = [];
        foreach ($instances as $n) {
            $base = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$n";
            $params[] = ["$base.SSID", $ssid, 'xsd:string'];
            if ($setPass) $params[] = ["$base.PreSharedKey.1.KeyPassphrase", $pass, 'xsd:string'];
        }

        $ok = acs_set_parameter_values($deviceId, $params);

        // ── Audit (tanpa menyimpan password) ──
        $me = current_user();
        db_insert('wifi_change_log', [
            'device_id'    => $deviceId,
            'pppoe'        => $dev['pppoe_username'],
            'pelanggan'    => $dev['pelanggan'],
            'teknisi_id'   => $me['id'] ?? null,
            'teknisi_nama' => $me['nama'] ?? null,
            'ssid_lama'    => $tgt['ssid'],
            'ssid_baru'    => $ssid,
            'ubah_ssid'    => ($ssid !== ($tgt['ssid'] ?? '')) ? 1 : 0,
            'ubah_pass'    => $setPass ? 1 : 0,
            'status'       => $ok ? 'terkirim' : 'gagal',
            'pesan'        => $ok
                ? ('Perintah dikirim ke ACS (' . count($instances) . ' radio).')
                : 'Gagal mengirim ke ACS.',
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        if ($ok) {
            echo json_encode([
                'ok'  => true,
                'msg' => 'Perubahan WiFi dikirim ke perangkat. Bila ONU online, berlaku dalam beberapa detik; bila offline, akan diterapkan saat online kembali.',
            ]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Gagal mengirim ke ACS. Cek koneksi ACS atau status perangkat.']);
        }
        exit;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Aksi tidak dikenal: ' . $action]);
}
