<?php
// ============================================================
//  KAHFINET - Wrapper Koneksi ACS (GenieACS, NBI REST API)
//  Semua fungsi di sini tidak pernah melempar exception ke caller.
//  Kegagalan dicatat ke logs/acs.log dan dikembalikan sebagai
//  null/false supaya fitur lain tetap jalan walau ACS offline.
// ============================================================

function acs_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(__DIR__ . '/../logs/acs.log', $line, FILE_APPEND);
}

function acs_active_config(): ?array
{
    $row = db_row("SELECT * FROM acs_settings WHERE is_active = 1 ORDER BY id LIMIT 1");
    if (!$row || empty($row['base_url'])) return null;
    if (!empty($row['password'])) {
        $row['password'] = decrypt_pppoe($row['password']);
    }
    return $row;
}

// Helper request generik ke NBI API. Return null kalau gagal konek/timeout.
function acs_request(string $method, string $path, array $query = [], $body = null): ?array
{
    $config = acs_active_config();
    if (!$config) return null;

    $url = rtrim($config['base_url'], '/') . $path;
    if ($query) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    if (!empty($config['username'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $config['username'] . ':' . ($config['password'] ?? ''));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        acs_log("Gagal konek ke $url: $error");
        return null;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        acs_log("HTTP $httpCode dari $url: " . substr($response, 0, 300));
        return null;
    }

    $decoded = $response !== '' ? json_decode($response, true) : [];
    return ['status' => $httpCode, 'data' => $decoded];
}

function acs_test_connection(): array
{
    $config = acs_active_config();
    if (!$config) {
        return ['ok' => false, 'msg' => 'Pengaturan ACS belum diisi.'];
    }

    $res = acs_request('GET', '/devices/', ['limit' => 1]);
    if ($res === null) {
        return ['ok' => false, 'msg' => 'Gagal konek ke ACS. Cek alamat, port, dan kredensial.'];
    }

    $count = is_array($res['data']) ? count($res['data']) : 0;
    return ['ok' => true, 'msg' => "Berhasil konek ke ACS. Contoh device ditemukan: $count."];
}

// ── Cache mirror: Device ONU/ONT (ACS → app, read-only) ──
function acs_fetch_devices(): ?array
{
    $res = acs_request('GET', '/devices/');
    if ($res === null || !is_array($res['data'])) return null;
    return $res['data'];
}

// Upsert berdasarkan device_id (bukan truncate+insert) supaya `id` baris yang
// sudah dipakai sebagai mapping manual (mikrotik_secrets_cache.genieacs_device_id)
// tidak berubah saat sync ulang.
function acs_sync_devices(): array
{
    $rows = acs_fetch_devices();
    if ($rows === null) {
        return ['ok' => false, 'msg' => 'Gagal konek ke ACS. Cek pengaturan koneksi di menu ACS > Pengaturan.'];
    }

    $deviceIds = [];

    foreach ($rows as $row) {
        $deviceId = $row['_id'] ?? '';
        if ($deviceId === '') continue;
        $deviceIds[] = $deviceId;

        $lastInform = $row['_lastInform'] ?? null;
        $data = [
            'tag'            => $row['_tags'][0] ?? null,
            'manufacturer'   => $row['_deviceId']['_Manufacturer'] ?? null,
            'product_class'  => $row['_deviceId']['_ProductClass'] ?? null,
            'pppoe_username' => $row['VirtualParameters']['pppoeUsername']['_value'] ?? null,
            'last_inform'    => $lastInform ? date('Y-m-d H:i:s', strtotime($lastInform)) : null,
            'raw'            => json_encode($row, JSON_UNESCAPED_UNICODE),
            'synced_at'      => date('Y-m-d H:i:s'),
        ];

        $existing = db_row("SELECT id FROM genieacs_devices_cache WHERE device_id = ?", [$deviceId]);
        if ($existing) {
            db_update('genieacs_devices_cache', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['device_id'] = $deviceId;
            db_insert('genieacs_devices_cache', $data);
        }
    }

    if ($deviceIds) {
        $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
        db_query("DELETE FROM genieacs_devices_cache WHERE device_id NOT IN ($placeholders)", $deviceIds);
    } else {
        db_query('DELETE FROM genieacs_devices_cache');
    }

    $count = count($rows);
    return ['ok' => true, 'msg' => "Berhasil sync $count device dari ACS."];
}

function acs_reboot_device(string $deviceId): bool
{
    $res = acs_request('POST', '/devices/' . rawurlencode($deviceId) . '/tasks', ['connection_request' => ''], ['name' => 'reboot']);
    return $res !== null;
}
