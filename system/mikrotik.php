<?php
// ============================================================
//  KAHFINET - Wrapper Sinkronisasi Mikrotik (PPP Profile & Secret)
//  Semua fungsi di sini tidak pernah melempar exception ke caller.
//  Kegagalan dicatat ke logs/mikrotik.log dan dikembalikan sebagai
//  null/false supaya CRUD lokal tetap jalan walau router offline.
// ============================================================

require_once __DIR__ . '/RouterosApi.php';

function mikrotik_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(__DIR__ . '/../logs/mikrotik.log', $line, FILE_APPEND);
}

function mikrotik_active_router(): ?array
{
    $row = db_row("SELECT * FROM mikrotik_routers WHERE is_active = 1 ORDER BY id LIMIT 1");
    if (!$row || empty($row['host']) || empty($row['username'])) return null;
    $row['password'] = decrypt_pppoe($row['password']);
    return $row;
}

function mikrotik_client(): ?RouterosApi
{
    $router = mikrotik_active_router();
    if (!$router) return null;

    try {
        $api = new RouterosApi();
        $api->connect($router['host'], (int)$router['api_port'], (bool)$router['use_ssl']);
        $api->login($router['username'], $router['password']);
        return $api;
    } catch (Throwable $e) {
        mikrotik_log('Koneksi gagal: ' . $e->getMessage());
        return null;
    }
}

// ── PPP Secret: dipakai cron isolir untuk disable secret yang menunggak ──
function mikrotik_secret_set_disabled(string $secretId, bool $disabled): bool
{
    $api = mikrotik_client();
    if (!$api) return false;

    try {
        $api->comm('/ppp/secret/set', [
            '.id'      => $secretId,
            'disabled' => $disabled ? 'yes' : 'no',
        ]);
        $api->close();
        return true;
    } catch (Throwable $e) {
        mikrotik_log('Gagal set disabled secret (' . $secretId . '): ' . $e->getMessage());
        return false;
    }
}

// ── PPP Secret: push profile + disabled sekaligus, dipakai saat status/paket
//    pelanggan berubah lewat halaman Status Pelanggan ──
function mikrotik_secret_push_profile(string $secretId, string $profileName, bool $disabled): bool
{
    $api = mikrotik_client();
    if (!$api) return false;

    try {
        $api->comm('/ppp/secret/set', [
            '.id'      => $secretId,
            'profile'  => $profileName,
            'disabled' => $disabled ? 'yes' : 'no',
        ]);
        $api->close();
        return true;
    } catch (Throwable $e) {
        mikrotik_log('Gagal push profile secret (' . $secretId . '): ' . $e->getMessage());
        return false;
    }
}

// ── Cache mirror: Profile & Secret (Mikrotik → app, read-only) ──

function mikrotik_fetch_profiles(): ?array
{
    $api = mikrotik_client();
    if (!$api) return null;

    try {
        $rows = $api->comm('/ppp/profile/print');
        $api->close();
        return $rows;
    } catch (Throwable $e) {
        mikrotik_log('Gagal ambil daftar profile: ' . $e->getMessage());
        return null;
    }
}

function mikrotik_fetch_secrets(): ?array
{
    $api = mikrotik_client();
    if (!$api) return null;

    try {
        $rows = $api->comm('/ppp/secret/print', ['?service' => 'pppoe']);
        $api->close();
        return $rows;
    } catch (Throwable $e) {
        mikrotik_log('Gagal ambil daftar secret: ' . $e->getMessage());
        return null;
    }
}

// Upsert berdasarkan ros_id (bukan truncate+insert) supaya `id` baris yang sudah
// dipakai sebagai link oleh paket/pelanggan tidak berubah saat sync ulang.
function mikrotik_sync_profiles(): array
{
    $rows = mikrotik_fetch_profiles();
    if ($rows === null) {
        return ['ok' => false, 'msg' => 'Gagal konek ke Mikrotik. Cek koneksi router di menu Pengaturan.'];
    }

    $routerId = mikrotik_active_router()['id'] ?? null;
    $rosIds   = [];

    foreach ($rows as $row) {
        $rosId = $row['.id'] ?? '';
        if ($rosId === '') continue;
        $rosIds[] = $rosId;

        $data = [
            'mikrotik_router_id' => $routerId,
            'name'               => $row['name'] ?? '',
            'rate_limit'         => $row['rate-limit'] ?? null,
            'raw'                => json_encode($row, JSON_UNESCAPED_UNICODE),
            'synced_at'          => date('Y-m-d H:i:s'),
        ];

        $existing = db_row("SELECT id FROM mikrotik_profiles_cache WHERE ros_id = ?", [$rosId]);
        if ($existing) {
            db_update('mikrotik_profiles_cache', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['ros_id'] = $rosId;
            db_insert('mikrotik_profiles_cache', $data);
        }
    }

    if ($rosIds) {
        $placeholders = implode(',', array_fill(0, count($rosIds), '?'));
        db_query("DELETE FROM mikrotik_profiles_cache WHERE ros_id NOT IN ($placeholders)", $rosIds);
    } else {
        db_query('DELETE FROM mikrotik_profiles_cache');
    }

    $count = count($rows);
    return ['ok' => true, 'msg' => "Berhasil sync $count PPP profile dari Mikrotik."];
}

function mikrotik_is_online(): bool
{
    $api = mikrotik_client();
    if (!$api) return false;
    try { $api->close(); } catch (Throwable) {}
    return true;
}

function mikrotik_sync_secrets(): array
{
    $rows = mikrotik_fetch_secrets();
    if ($rows === null) {
        return ['ok' => false, 'msg' => 'Gagal konek ke Mikrotik. Cek koneksi router di menu Pengaturan.'];
    }

    $routerId = mikrotik_active_router()['id'] ?? null;
    $rosIds   = [];

    foreach ($rows as $row) {
        $rosId = $row['.id'] ?? '';
        if ($rosId === '') continue;
        $rosIds[] = $rosId;

        $data = [
            'mikrotik_router_id' => $routerId,
            'name'               => $row['name'] ?? '',
            'service'            => $row['service'] ?? null,
            'profile'            => $row['profile'] ?? null,
            'remote_address'     => $row['remote-address'] ?? null,
            'disabled'           => (($row['disabled'] ?? 'false') === 'true') ? 1 : 0,
            'comment'            => $row['comment'] ?? null,
            'raw'                => json_encode($row, JSON_UNESCAPED_UNICODE),
            'synced_at'          => date('Y-m-d H:i:s'),
        ];

        $existing = db_row("SELECT id FROM mikrotik_secrets_cache WHERE ros_id = ?", [$rosId]);
        if ($existing) {
            db_update('mikrotik_secrets_cache', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['ros_id'] = $rosId;
            db_insert('mikrotik_secrets_cache', $data);
        }
    }

    if ($rosIds) {
        $placeholders = implode(',', array_fill(0, count($rosIds), '?'));
        db_query("DELETE FROM mikrotik_secrets_cache WHERE ros_id NOT IN ($placeholders)", $rosIds);
    } else {
        db_query('DELETE FROM mikrotik_secrets_cache');
    }

    $count = count($rows);
    return ['ok' => true, 'msg' => "Berhasil sync $count PPP secret dari Mikrotik."];
}
