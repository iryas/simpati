<?php
// ============================================================
//  MONITORING JARINGAN — Helper Data (dari cache GenieACS)
// ============================================================
require_once __DIR__ . '/_init.php';

// Cari nilai numerik pertama dari parameter yang cocok regex (lintas merk ONU).
// Prioritas VirtualParameters, lalu telusuri seluruh tree.
function mon_find_value(?array $node, string $regex): ?float {
    if (!is_array($node)) return null;
    $found = null;
    $walk = function ($n) use (&$walk, &$found, $regex) {
        if ($found !== null || !is_array($n)) return;
        foreach ($n as $k => $v) {
            if ($found !== null) return;
            if (is_array($v) && array_key_exists('_value', $v)
                && preg_match($regex, (string)$k) && is_numeric($v['_value'])) {
                $found = (float)$v['_value']; return;
            }
            if (is_array($v)) $walk($v);
        }
    };
    if (isset($node['VirtualParameters'])) $walk($node['VirtualParameters']);
    if ($found === null) $walk($node);
    return $found;
}

// Sebaran jumlah pelanggan per area (untuk bar chart).
function mon_pelanggan_area(): array {
    $rows = db_rows(
        "SELECT a.nama, COUNT(p.id) c
         FROM area a
         LEFT JOIN pelanggan p ON p.area_id = a.id
         GROUP BY a.id, a.nama
         HAVING c > 0
         ORDER BY c DESC"
    );
    $out = [];
    foreach ($rows as $r) $out[($r['nama'] ?: '—')] = (int)$r['c'];
    return $out;
}

// Status (online/offline/isolir) pelanggan per area — untuk stacked bar.
function mon_status_per_area(): array {
    $m   = db_row("SELECT MAX(last_inform) x FROM genieacs_devices_cache");
    $ref = ($m && $m['x']) ? strtotime($m['x']) : time();

    $rows = db_rows(
        "SELECT a.nama AS area, p.status AS pstatus, g.last_inform AS li
         FROM pelanggan p
         JOIN area a ON a.id = p.area_id
         LEFT JOIN mikrotik_secrets_cache s ON s.id = p.mikrotik_secrets_id
         LEFT JOIN genieacs_devices_cache g ON LOWER(g.pppoe_username) = LOWER(s.name)
         WHERE p.status IN ('aktif','isolir')"
    );
    $areas = [];
    foreach ($rows as $r) {
        $area = $r['area'] ?: '—';
        $areas[$area] ??= ['Online' => 0, 'Offline' => 0, 'Isolir' => 0];
        if ($r['pstatus'] === 'isolir') { $areas[$area]['Isolir']++; continue; }
        $li = $r['li'] ? strtotime($r['li']) : 0;
        $areas[$area][($li > 0 && $li >= $ref - 300) ? 'Online' : 'Offline']++;
    }
    uasort($areas, fn($a, $b) => array_sum($b) <=> array_sum($a));
    return $areas;
}

// Top pemakaian bandwidth bulan berjalan (GB).
function mon_top_pemakaian(int $limit = 10): array {
    $bulan = bulan_tagihan_sekarang();
    $rows  = db_rows(
        "SELECT p.nama, u.bytes_out
         FROM usage_pppoe u JOIN pelanggan p ON p.id = u.pelanggan_id
         WHERE u.bulan_tagihan = ? AND u.bytes_out > 0
         ORDER BY u.bytes_out DESC LIMIT " . max(1, min(30, $limit)),
        [$bulan]
    );
    $out = [];
    foreach ($rows as $r) $out[$r['nama']] = round((int)$r['bytes_out'] / (1024 * 1024 * 1024), 2);
    return $out;
}

// Format bytes → string otomatis (B / KB / MB / GB / TB).
function mon_fmt_bytes(int $b, int $dec = 2): string {
    if ($b <= 0) return '0 B';
    $u = ['B','KB','MB','GB','TB'];
    $i = (int)floor(log($b, 1024));
    $i = min($i, count($u) - 1);
    return number_format($b / (1024 ** $i), $dec) . ' ' . $u[$i];
}

// Data pemakaian bandwidth lengkap per pelanggan & area untuk bulan tertentu.
// $bulan: format 'Y-m', default bulan tagihan berjalan.
// Kembalikan: summary, daftar pelanggan (diurutkan terbesar), breakdown per area, daftar bulan.
function mon_pemakaian_data(string $bulan = ''): array {
    if (!$bulan) $bulan = bulan_tagihan_sekarang();

    // Daftar bulan yang punya data (maks 12 bulan terakhir) untuk dropdown.
    $bulanRows  = db_rows(
        "SELECT DISTINCT bulan_tagihan FROM usage_pppoe
         WHERE bytes_out > 0 ORDER BY bulan_tagihan DESC LIMIT 12"
    );
    $bulanList  = array_column($bulanRows, 'bulan_tagihan');
    if (!in_array($bulan, $bulanList) && !empty($bulanList)) $bulan = $bulanList[0];

    // Data per pelanggan.
    $rows = db_rows(
        "SELECT p.nama AS pelanggan, a.nama AS area,
                u.bytes_out, u.uptime_seconds, u.last_poll_at
         FROM usage_pppoe u
         JOIN pelanggan p ON p.id = u.pelanggan_id
         LEFT JOIN area a ON a.id = p.area_id
         WHERE u.bulan_tagihan = ? AND u.bytes_out > 0
         ORDER BY u.bytes_out DESC",
        [$bulan]
    );

    $totalBytes   = 0;
    $totalUptime  = 0;
    $areaBytes    = [];
    $areaCount    = [];
    $out          = [];

    foreach ($rows as $idx => $r) {
        $bytes  = (int)$r['bytes_out'];
        $uptime = (int)$r['uptime_seconds'];
        $area   = $r['area'] ?: '—';
        $totalBytes  += $bytes;
        $totalUptime += $uptime;

        $areaBytes[$area] = ($areaBytes[$area] ?? 0) + $bytes;
        $areaCount[$area] = ($areaCount[$area] ?? 0) + 1;

        $out[] = [
            'rank'       => $idx + 1,
            'pelanggan'  => $r['pelanggan'] ?: '—',
            'area'       => $area,
            'bytes_out'  => $bytes,
            'uptime_sec' => $uptime,
            'last_poll'  => $r['last_poll_at'] ?? null,
        ];
    }

    // Breakdown per area (diurutkan terbesar).
    arsort($areaBytes);
    $areas = [];
    foreach ($areaBytes as $aName => $aBytes) {
        $areas[] = [
            'area'  => $aName,
            'bytes' => $aBytes,
            'count' => $areaCount[$aName],
        ];
    }

    $count = count($out);
    return [
        'bulan'       => $bulan,
        'bulan_list'  => $bulanList,
        'total_bytes' => $totalBytes,
        'avg_bytes'   => $count > 0 ? (int)($totalBytes / $count) : 0,
        'count'       => $count,
        'rows'        => $out,
        'areas'       => $areas,
    ];
}

// Daftar semua ONU + status + RXPower + pelanggan/area (untuk tabel).
function mon_onu_list(): array {
    $m   = db_row("SELECT MAX(last_inform) x FROM genieacs_devices_cache");
    $ref = ($m && $m['x']) ? strtotime($m['x']) : time();

    $rows = db_rows(
        "SELECT g.device_id, g.manufacturer, g.product_class, g.last_inform, g.pppoe_username, g.raw,
                p.nama AS pelanggan, p.status AS pstatus, a.nama AS area
         FROM genieacs_devices_cache g
         LEFT JOIN mikrotik_secrets_cache s ON LOWER(s.name) = LOWER(g.pppoe_username)
         LEFT JOIN pelanggan p ON p.mikrotik_secrets_id = s.id
         LEFT JOIN area a ON a.id = p.area_id"
    );

    $out = [];
    foreach ($rows as $r) {
        if (empty($r['area'])) continue; // Lewati ONU tanpa area.
        $j  = $r['raw'] ? json_decode($r['raw'], true) : null;
        $rx = $j ? mon_find_value($j, '/RXPower/i') : null;
        $li = $r['last_inform'] ? strtotime($r['last_inform']) : 0;
        $online = $li > 0 && $li >= $ref - 300;
        $status = ($r['pstatus'] === 'isolir') ? 'isolir' : ($online ? 'online' : 'offline');
        $out[] = [
            'device_id'   => $r['device_id'],
            'pelanggan'   => $r['pelanggan'] ?: '—',
            'pppoe'       => $r['pppoe_username'] ?: '',
            'area'        => $r['area'] ?: '—',
            'model'       => $r['product_class'] ?: ($r['manufacturer'] ?: '—'),
            'status'      => $status,
            'rx'          => $rx,
            'last_inform' => $r['last_inform'],
        ];
    }
    // Urut: bermasalah dulu (offline/isolir), lalu per nama.
    usort($out, function ($a, $b) {
        $rank = ['offline' => 0, 'isolir' => 1, 'online' => 2];
        return [$rank[$a['status']], $a['pelanggan']] <=> [$rank[$b['status']], $b['pelanggan']];
    });
    return $out;
}

// ONU offline dikelompokkan per area (untuk modul Offline).
// Kembalikan: ['ref' => int, 'total' => int, 'areas' => [namaArea => [list ONU]]].
function mon_offline_by_area(): array {
    $m   = db_row("SELECT MAX(last_inform) x FROM genieacs_devices_cache");
    $ref = ($m && $m['x']) ? strtotime($m['x']) : time();

    $rows = db_rows(
        "SELECT g.device_id, g.manufacturer, g.product_class, g.last_inform, g.pppoe_username, g.raw,
                p.nama AS pelanggan, p.status AS pstatus, p.no_hp, a.nama AS area
         FROM genieacs_devices_cache g
         LEFT JOIN mikrotik_secrets_cache s ON LOWER(s.name) = LOWER(g.pppoe_username)
         LEFT JOIN pelanggan p ON p.mikrotik_secrets_id = s.id
         LEFT JOIN area a ON a.id = p.area_id"
    );

    $areas = [];
    $total = 0;
    foreach ($rows as $r) {
        if (empty($r['area'])) continue; // Lewati ONU tanpa area.
        $li     = $r['last_inform'] ? strtotime($r['last_inform']) : 0;
        $online = $li > 0 && $li >= $ref - 300;
        $status = ($r['pstatus'] === 'isolir') ? 'isolir' : ($online ? 'online' : 'offline');
        if ($status !== 'offline') continue;

        $area = $r['area'] ?: '—';
        $j    = $r['raw'] ? json_decode($r['raw'], true) : null;
        $rx   = $j ? mon_find_value($j, '/RXPower/i') : null;

        $areas[$area][] = [
            'device_id'   => $r['device_id'],
            'pelanggan'   => $r['pelanggan'] ?: '—',
            'pppoe'       => $r['pppoe_username'] ?: '',
            'no_hp'       => $r['no_hp'] ?? null,
            'model'       => $r['product_class'] ?: ($r['manufacturer'] ?: '—'),
            'rx'          => $rx,
            'last_inform' => $r['last_inform'],
            'durasi_detik'=> $li > 0 ? ($ref - $li) : null,
        ];
        $total++;
    }

    // Urutkan: area paling banyak offline teratas; dalam area urutkan per last_inform terlama.
    uasort($areas, fn($a, $b) => count($b) <=> count($a));
    foreach ($areas as &$list) {
        usort($list, fn($a, $b) => ($a['durasi_detik'] ?? 0) <=> ($b['durasi_detik'] ?? 0));
        $list = array_reverse($list); // terlama offline → atas
    }
    unset($list);

    return ['ref' => $ref, 'total' => $total, 'areas' => $areas];
}

// ONU bermasalah berdasarkan RXPower — early warning sebelum putus (untuk modul Signal).
// Hanya return kategori: kritis (< −27 dBm) dan waspada (−27 s/d −25 dBm).
// Counts mencakup semua kategori untuk ditampilkan sebagai statistik konteks.
function mon_signal_list(): array {
    $m   = db_row("SELECT MAX(last_inform) x FROM genieacs_devices_cache");
    $ref = ($m && $m['x']) ? strtotime($m['x']) : time();

    $rows = db_rows(
        "SELECT g.device_id, g.manufacturer, g.product_class, g.last_inform, g.pppoe_username, g.raw,
                p.nama AS pelanggan, p.status AS pstatus, a.nama AS area
         FROM genieacs_devices_cache g
         LEFT JOIN mikrotik_secrets_cache s ON LOWER(s.name) = LOWER(g.pppoe_username)
         LEFT JOIN pelanggan p ON p.mikrotik_secrets_id = s.id
         LEFT JOIN area a ON a.id = p.area_id"
    );

    $counts = ['kritis' => 0, 'waspada' => 0, 'bagus' => 0, 'terlalu_kuat' => 0, 'no_data' => 0];
    $total_all = 0; // total semua ONU (termasuk yang sinyal aman)
    $out    = [];

    foreach ($rows as $r) {
        if (empty($r['area'])) continue; // Lewati ONU tanpa area.
        $total_all++;
        $li     = $r['last_inform'] ? strtotime($r['last_inform']) : 0;
        $online = $li > 0 && $li >= $ref - 300;
        $status = ($r['pstatus'] === 'isolir') ? 'isolir' : ($online ? 'online' : 'offline');

        $j  = $r['raw'] ? json_decode($r['raw'], true) : null;
        $rx = $j ? mon_find_value($j, '/RXPower/i') : null;

        if ($rx === null)       { $cat = 'no_data';      $sortKey = 5; }
        elseif ($rx < -27)      { $cat = 'kritis';       $sortKey = 1; }
        elseif ($rx < -25)      { $cat = 'waspada';      $sortKey = 2; }
        elseif ($rx <= -8)      { $cat = 'bagus';        $sortKey = 3; }
        else                    { $cat = 'terlalu_kuat'; $sortKey = 4; }

        $counts[$cat]++;

        // Hanya masukkan ke daftar jika sinyal bermasalah (early warning).
        if (!in_array($cat, ['kritis', 'waspada'])) continue;

        $out[] = [
            'device_id'   => $r['device_id'],
            'pelanggan'   => $r['pelanggan'] ?: '—',
            'pppoe'       => $r['pppoe_username'] ?: '',
            'area'        => $r['area'] ?: '—',
            'model'       => $r['product_class'] ?: ($r['manufacturer'] ?: '—'),
            'status'      => $status,
            'rx'          => $rx,
            'kategori'    => $cat,
            'last_inform' => $r['last_inform'],
            '_sort'       => [$sortKey, $rx ?? 999], // kritis terkecil → atas
        ];
    }

    // Urutkan: kritis dulu, dalam kategori rx terkecil (paling lemah) → atas.
    usort($out, fn($a, $b) => $a['_sort'] <=> $b['_sort']);

    return ['ref' => $ref, 'total_all' => $total_all, 'total' => count($out), 'counts' => $counts, 'rows' => $out];
}

// Warna kualitas RXPower (dBm) → [css-var, teks].
function mon_rx_style(?float $x): array {
    if ($x === null) return ['--muted', '—'];
    if ($x > -8)     return ['--warn',  number_format($x, 1)];   // terlalu kuat
    if ($x >= -25)   return ['--green', number_format($x, 1)];   // bagus
    if ($x >= -27)   return ['--amber', number_format($x, 1)];   // waspada
    return ['--red', number_format($x, 1)];                      // kritis
}

// Warna suhu ONU (°C) → [css-var, label].
function mon_temp_style(?float $t): array {
    if ($t === null) return ['--muted', '—'];
    if ($t < 55)     return ['--green', 'Normal'];
    if ($t <= 70)    return ['--amber', 'Hangat'];
    return ['--red', 'Panas'];
}

// Ambil string _value dari sebuah node param (VirtualParameters / DeviceInfo).
function mon_str(?array $node): ?string {
    if (!is_array($node)) return null;
    $v = $node['_value'] ?? null;
    return ($v === null || $v === '') ? null : (string)$v;
}

// Detail satu ONU (untuk halaman detail perangkat).
function mon_onu_detail(string $deviceId): ?array {
    $r = db_row(
        "SELECT g.device_id, g.manufacturer, g.product_class, g.last_inform, g.pppoe_username, g.synced_at, g.raw,
                p.nama AS pelanggan, p.status AS pstatus, p.no_hp, p.alamat, a.nama AS area
         FROM genieacs_devices_cache g
         LEFT JOIN mikrotik_secrets_cache s ON LOWER(s.name) = LOWER(g.pppoe_username)
         LEFT JOIN pelanggan p ON p.mikrotik_secrets_id = s.id
         LEFT JOIN area a ON a.id = p.area_id
         WHERE g.device_id = ? LIMIT 1",
        [$deviceId]
    );
    if (!$r) return null;

    $m   = db_row("SELECT MAX(last_inform) x FROM genieacs_devices_cache");
    $ref = ($m && $m['x']) ? strtotime($m['x']) : time();
    $li  = $r['last_inform'] ? strtotime($r['last_inform']) : 0;
    $online = $li > 0 && $li >= $ref - 300;

    $j    = $r['raw'] ? json_decode($r['raw'], true) : [];
    $vp   = $j['VirtualParameters'] ?? [];
    $di   = $j['InternetGatewayDevice']['DeviceInfo'] ?? [];
    $wlan = $j['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration'] ?? [];

    return [
        'device_id'   => $r['device_id'],
        'pelanggan'   => $r['pelanggan'] ?: '—',
        'no_hp'       => $r['no_hp'] ?? null,
        'alamat'      => $r['alamat'] ?? null,
        'area'        => $r['area'] ?: '—',
        'pppoe'       => $r['pppoe_username'] ?: '',
        'status'      => ($r['pstatus'] === 'isolir') ? 'isolir' : ($online ? 'online' : 'offline'),
        'last_inform' => $r['last_inform'],
        'synced_at'   => $r['synced_at'] ?? null,
        'rx'          => mon_find_value($j, '/RXPower/i'),
        'temp'        => mon_find_value($j, '/TransceiverTemperature/i') ?? mon_str($vp['gettemp'] ?? null),
        'uptime_dev'  => mon_str($vp['getdeviceuptime'] ?? null),
        'uptime_ppp'  => mon_str($vp['getpppuptime'] ?? null),
        'ponmode'     => mon_str($vp['getponmode'] ?? null),
        'devices'     => mon_str($vp['activedevices'] ?? null),
        'ip_lokal'    => mon_str($vp['IPTR069'] ?? null),
        'wifi_ssid'   => mon_str($wlan['1']['SSID'] ?? null),
        // Utamakan path TR-069 langsung (cakupan lebih luas), fallback ke VirtualParameter.
        'wifi_pass'   => mon_str($wlan['1']['PreSharedKey']['1']['KeyPassphrase'] ?? null)
                      ?: mon_str($wlan['1']['KeyPassphrase'] ?? null)
                      ?: mon_str($vp['WlanPassword'] ?? null),
        'serial'      => mon_str($vp['getSerialNumber'] ?? null) ?: mon_str($di['SerialNumber'] ?? null),
        'manufacturer'=> $r['manufacturer'] ?: mon_str($di['Manufacturer'] ?? null),
        'model'       => $r['product_class'] ?: mon_str($di['ModelName'] ?? null),
        'sw_ver'      => mon_str($di['SoftwareVersion'] ?? null),
        'hw_ver'      => mon_str($di['HardwareVersion'] ?? null),
    ];
}

// Tentukan instance WLANConfiguration yang jadi target ubah WiFi utama:
// slot .1 (2.4GHz primary) + slot lain yang SSID-nya sama (mis. .5 = 5GHz twin),
// supaya 2.4G & 5G ikut berubah tapi SSID sekunder (guest) tidak tersentuh.
// Kembalikan ['ssid' => <ssid .1 saat ini>, 'instances' => ['1','5',...]].
function mon_wifi_targets(string $deviceId): array {
    $r = db_row("SELECT raw FROM genieacs_devices_cache WHERE device_id = ? LIMIT 1", [$deviceId]);
    if (!$r) return ['ssid' => null, 'instances' => []];
    $j    = json_decode($r['raw'], true) ?: [];
    $wlan = $j['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration'] ?? [];
    $primary   = mon_str($wlan['1']['SSID'] ?? null);
    $instances = [];
    if (isset($wlan['1'])) $instances[] = '1';
    if ($primary !== null) {
        foreach ($wlan as $k => $cfg) {
            if ($k === '1' || !is_array($cfg)) continue;
            if (mon_str($cfg['SSID'] ?? null) === $primary) $instances[] = (string)$k;
        }
    }
    return ['ssid' => $primary, 'instances' => array_values(array_unique($instances))];
}

// Ringkasan fleet ONU untuk grafik pie.
function mon_overview(): array {
    $rows = db_rows("SELECT manufacturer, product_class, last_inform, raw FROM genieacs_devices_cache");

    $jenis  = [];
    $status = ['Online' => 0, 'Offline' => 0];
    $rx     = ['Bagus' => 0, 'Waspada' => 0, 'Kritis' => 0, 'Terlalu kuat' => 0, 'Tak ada data' => 0];
    $temp   = ['Normal' => 0, 'Hangat' => 0, 'Panas' => 0, 'Tak ada data' => 0];

    $parsed = [];
    $maxInform = 0;
    foreach ($rows as $r) {
        $li = $r['last_inform'] ? strtotime($r['last_inform']) : 0;
        if ($li > $maxInform) $maxInform = $li;
        $j = $r['raw'] ? json_decode($r['raw'], true) : null;
        $parsed[] = [
            'li'    => $li,
            'rx'    => $j ? mon_find_value($j, '/RXPower/i') : null,
            't'     => $j ? (mon_find_value($j, '/TransceiverTemperature/i') ?? mon_find_value($j, '/Temperature/i')) : null,
            'model' => trim(($r['product_class'] ?: ($r['manufacturer'] ?: 'Lainnya'))),
        ];
    }
    // Referensi "online" = sekitar sync terakhir (data cache = snapshot).
    $ref = $maxInform ?: time();

    foreach ($parsed as $p) {
        $jenis[$p['model']] = ($jenis[$p['model']] ?? 0) + 1;

        $status[($p['li'] > 0 && $p['li'] >= $ref - 300) ? 'Online' : 'Offline']++;

        $x = $p['rx'];
        if ($x === null)       $rx['Tak ada data']++;
        elseif ($x > -8)       $rx['Terlalu kuat']++;
        elseif ($x >= -25)     $rx['Bagus']++;
        elseif ($x >= -27)     $rx['Waspada']++;
        else                   $rx['Kritis']++;

        $t = $p['t'];
        if ($t === null)  $temp['Tak ada data']++;
        elseif ($t < 55)  $temp['Normal']++;
        elseif ($t <= 70) $temp['Hangat']++;
        else              $temp['Panas']++;
    }
    arsort($jenis);

    return ['total' => count($rows), 'ref' => $ref, 'jenis' => $jenis, 'status' => $status, 'rx' => $rx, 'temp' => $temp];
}
