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

// Berapa hari total periode tagihan (Y-m) berlangsung, dan sudah berjalan
// berapa hari sampai sekarang — dipakai untuk hitung rata-rata PER HARI
// (bytes_out itu kumulatif sejak awal periode, bukan snapshot 1 hari).
// Pakai batas tgl_mulai_tagihan yang sama seperti label_periode_tagihan().
function mon_periode_progres(string $bulan_ym): array {
    $tgl_mulai = (int)app_setting('tgl_mulai_tagihan', '1');
    $ts   = strtotime($bulan_ym . '-01');
    $bln  = (int)date('n', $ts);
    $thn  = (int)date('Y', $ts);
    $tsStart = mktime(0, 0, 0, $bln, $tgl_mulai, $thn);
    $tsEnd   = mktime(0, 0, 0, $bln + 1, $tgl_mulai, $thn); // awal hari SETELAH periode berakhir

    $hariTotal = (int)round(($tsEnd - $tsStart) / 86400);

    $now = time();
    if ($now >= $tsEnd) {
        $hariBerjalan = $hariTotal;           // periode sudah lewat (bulan lampau)
    } elseif ($now < $tsStart) {
        $hariBerjalan = 0;                    // periode belum mulai (jarang terjadi)
    } else {
        $hariBerjalan = (int)floor(($now - $tsStart) / 86400) + 1;
    }

    return [
        'hari_total'    => $hariTotal,
        'hari_berjalan' => max(1, $hariBerjalan),
    ];
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
        "SELECT p.id AS pelanggan_id, p.nama AS pelanggan, a.nama AS area,
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
            'rank'          => $idx + 1,
            'pelanggan_id'  => (int)$r['pelanggan_id'],
            'pelanggan'     => $r['pelanggan'] ?: '—',
            'area'          => $area,
            'bytes_out'     => $bytes,
            'uptime_sec'    => $uptime,
            'last_poll'     => $r['last_poll_at'] ?? null,
        ];
    }

    // Progres periode & rata-rata harian (bytes_out kumulatif ÷ hari berjalan).
    $progres      = mon_periode_progres($bulan);
    $hariBerjalan = $progres['hari_berjalan'];
    foreach ($out as &$row) {
        $row['bytes_per_hari'] = (int)round($row['bytes_out'] / $hariBerjalan);
    }
    unset($row);

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
        'bulan'          => $bulan,
        'bulan_list'     => $bulanList,
        'total_bytes'    => $totalBytes,
        'avg_bytes'      => $count > 0 ? (int)($totalBytes / $count) : 0,
        'avg_bytes_hari' => $count > 0 ? (int)round(($totalBytes / $count) / $hariBerjalan) : 0,
        'hari_total'     => $progres['hari_total'],
        'hari_berjalan'  => $hariBerjalan,
        'count'          => $count,
        'rows'           => $out,
        'areas'          => $areas,
    ];
}

// Rincian pemakaian PER TANGGAL untuk 1 pelanggan, dalam periode tagihan
// $bulan_ym. Dipakai modal "Detail Pemakaian Harian" di modul Pemakaian.
// Tanggal yang belum ada baris di usage_pppoe_harian (sebelum fitur ini
// jalan, atau pelanggan offline waktu itu) ditandai bytes_out/uptime_seconds
// null — TIDAK direkonstruksi/diestimasi, biar jujur ke datanya.
function mon_pemakaian_harian(int $pelanggan_id, string $bulan_ym): array {
    $pelanggan = db_row(
        "SELECT p.nama, a.nama AS area FROM pelanggan p
         LEFT JOIN area a ON a.id = p.area_id WHERE p.id = ?",
        [$pelanggan_id]
    );
    if (!$pelanggan) {
        return ['ok' => false, 'error' => 'Pelanggan tidak ditemukan'];
    }

    $tgl_mulai = (int)app_setting('tgl_mulai_tagihan', '1');
    $ts   = strtotime($bulan_ym . '-01');
    if ($ts === false) {
        return ['ok' => false, 'error' => 'Bulan tidak valid'];
    }
    $bln  = (int)date('n', $ts);
    $thn  = (int)date('Y', $ts);
    $tsStart = mktime(0, 0, 0, $bln, $tgl_mulai, $thn);
    $tsEnd   = mktime(0, 0, 0, $bln + 1, $tgl_mulai, $thn); // eksklusif
    $tsBatas = min($tsEnd, strtotime('tomorrow')); // jangan tampilin tanggal yang belum terjadi

    $rows = db_rows(
        "SELECT tanggal, bytes_out, uptime_seconds FROM usage_pppoe_harian
         WHERE pelanggan_id = ? AND tanggal >= ? AND tanggal < ?
         ORDER BY tanggal ASC",
        [$pelanggan_id, date('Y-m-d', $tsStart), date('Y-m-d', $tsEnd)]
    );
    $map = [];
    foreach ($rows as $r) $map[$r['tanggal']] = $r;

    $hariIni = date('Y-m-d');
    $hari    = [];
    for ($t = $tsStart; $t < $tsBatas; $t += 86400) {
        $tgl = date('Y-m-d', $t);
        $ada = isset($map[$tgl]);
        $hari[] = [
            'tanggal'        => $tgl,
            'bytes_out'      => $ada ? (int)$map[$tgl]['bytes_out'] : null,
            'uptime_seconds' => $ada ? (int)$map[$tgl]['uptime_seconds'] : null,
            'hari_ini'       => $tgl === $hariIni,
        ];
    }

    // Tanggal pertama yang punya data — buat catatan "mulai tercatat sejak".
    $mulaiTercatat = null;
    foreach ($hari as $h) {
        if ($h['bytes_out'] !== null) { $mulaiTercatat = $h['tanggal']; break; }
    }

    return [
        'ok'             => true,
        'pelanggan'      => $pelanggan['nama'],
        'area'           => $pelanggan['area'] ?: '—',
        'periode_label'  => label_periode_tagihan($bulan_ym, $tgl_mulai),
        'mulai_tercatat' => $mulaiTercatat,
        'hari'           => $hari,
    ];
}

// Format durasi detik → string manusiawi (dipakai pesan alert).
function mon_fmt_durasi(?int $sek): string {
    if ($sek === null || $sek <= 0) return '—';
    if ($sek < 60)    return $sek . 'd';
    if ($sek < 3600)  return floor($sek / 60) . 'mnt';
    if ($sek < 86400) return floor($sek / 3600) . 'j ' . floor(($sek % 3600) / 60) . 'mnt';
    return floor($sek / 86400) . 'hr ' . floor(($sek % 86400) / 3600) . 'j';
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
        // ONU tanpa area (PPPoE tak ketemu di Mikrotik, secret tak terhubung ke
        // pelanggan, atau pelanggan belum punya area) TETAP ditampilkan — ditandai
        // 'mapped' => false, bukan disembunyikan diam-diam (bisa jadi ONU basi
        // yang belum dihapus di ACS, atau instalasi baru yang belum di-mapping).
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
            'mapped'      => !empty($r['area']),
        ];
    }
    // Urut: yang ke-mapping dulu (bermasalah di atas, lalu per nama), ONU
    // belum ter-mapping ditaruh paling bawah (bukan prioritas kerja harian).
    usort($out, function ($a, $b) {
        $rank = ['offline' => 0, 'isolir' => 1, 'online' => 2];
        $am   = $a['mapped'] ? 0 : 1;
        $bm   = $b['mapped'] ? 0 : 1;
        return [$am, $rank[$a['status']], $a['pelanggan']] <=> [$bm, $rank[$b['status']], $b['pelanggan']];
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
// $includeAll: kalau true, sertakan SEMUA kategori (termasuk 'bagus'/aman) —
// dipakai pengecekan alert untuk mendeteksi ONU yang sinyalnya sudah pulih.
// Default false (perilaku lama, hanya kritis+waspada) dipakai halaman UI.
function mon_signal_list(bool $includeAll = false): array {
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

        // Hanya masukkan ke daftar jika sinyal bermasalah (early warning),
        // kecuali diminta semua kategori.
        if (!$includeAll && !in_array($cat, ['kritis', 'waspada'])) continue;

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

// ============================================================
//  ALERT TELEGRAM — hanya kirim saat status BERUBAH (anti-spam)
//  Dipanggil dari worker.php sesudah tiap siklus acs:sync.
// ============================================================

// Ambang batas (detik/jumlah) — diseragamkan di sini biar gampang diubah.
const MON_ALERT_CLUSTER_MIN   = 3;     // ONU offline bareng di 1 area → alert cluster
const MON_ALERT_OFFLINE_MIN_S = 1800;  // 30 menit → alert individual (di luar cluster)

function mon_alert_get_state(string $key): ?string {
    $r = db_row("SELECT state FROM monitor_alert_state WHERE alert_key = ?", [$key]);
    return $r ? $r['state'] : null;
}

function mon_alert_set_state(string $key, string $state, ?string $context = null): void {
    db_query(
        "INSERT INTO monitor_alert_state (alert_key, state, context, updated_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE state = VALUES(state), context = VALUES(context), updated_at = NOW()",
        [$key, $state, $context]
    );
}

function mon_alert_waktu(): string {
    return tgl_indo(date('Y-m-d H:i:s'), true);
}

// Cek perubahan status ONU offline: cluster per area + individual (>30 mnt),
// kirim notifikasi Telegram hanya saat status baru berubah (naik/pulih).
function mon_check_alerts_offline(): void {
    $data          = mon_offline_by_area();
    $clusterActive = [];

    // 1) Cluster per area.
    foreach ($data['areas'] as $area => $list) {
        $count = count($list);
        $key   = 'offline_cluster:' . $area;
        $old   = mon_alert_get_state($key);

        if ($count >= MON_ALERT_CLUSTER_MIN) {
            $clusterActive[$area] = true;
            if ($old !== 'active') {
                $nama = array_map(fn($o) => $o['pelanggan'], array_slice($list, 0, 5));
                $sisa = count($list) - count($nama);
                $msg  = "🔴 *GANGGUAN CLUSTER — {$area}*\n"
                      . "{$count} ONU offline bersamaan di area ini, kemungkinan gangguan listrik/kabel.\n\n"
                      . implode("\n", array_map(fn($n) => "• $n", $nama))
                      . ($sisa > 0 ? "\n+{$sisa} lainnya" : '')
                      . "\n\n🕐 " . mon_alert_waktu();
                kirim_telegram($msg);
                mon_alert_set_state($key, 'active', $area);
            }
        } elseif ($old === 'active') {
            $msg = "✅ *Cluster {$area} sudah pulih*\nONU di area ini sudah kembali online.\n\n🕐 " . mon_alert_waktu();
            kirim_telegram($msg);
            mon_alert_set_state($key, 'resolved', $area);
        }
    }

    // 2) Individual (>30 menit terus-menerus), skip area yang sedang cluster-aktif.
    $currentOfflineIds = [];
    foreach ($data['areas'] as $area => $list) {
        foreach ($list as $onu) {
            $currentOfflineIds[] = $onu['device_id'];
            if (isset($clusterActive[$area])) continue;

            $durasi = $onu['durasi_detik'] ?? 0;
            if ($durasi < MON_ALERT_OFFLINE_MIN_S) continue;

            $key = 'offline_individual:' . $onu['device_id'];
            if (mon_alert_get_state($key) === 'active') continue;

            $msg = "🟠 *ONU OFFLINE >30 menit*\n"
                 . "Pelanggan : {$onu['pelanggan']}\n"
                 . ($onu['pppoe'] ? "PPPoE     : {$onu['pppoe']}\n" : '')
                 . "Area      : {$area}\n"
                 . "Durasi    : " . mon_fmt_durasi($durasi) . "\n\n"
                 . "🕐 " . mon_alert_waktu();
            kirim_telegram($msg);
            mon_alert_set_state($key, 'active', $onu['pelanggan']);
        }
    }

    // 3) Pulih: individual yang tadinya offline-aktif, sekarang tidak offline lagi.
    $rows = db_rows("SELECT alert_key, context FROM monitor_alert_state WHERE alert_key LIKE 'offline\\_individual:%' AND state = 'active'");
    foreach ($rows as $r) {
        $deviceId = substr($r['alert_key'], strlen('offline_individual:'));
        if (in_array($deviceId, $currentOfflineIds, true)) continue;
        $msg = "✅ *{$r['context']} sudah online kembali*\n\n🕐 " . mon_alert_waktu();
        kirim_telegram($msg);
        mon_alert_set_state($r['alert_key'], 'resolved', $r['context']);
    }
}

// Cek perubahan kategori sinyal (kritis/waspada/aman) per ONU,
// kirim notifikasi hanya saat kategori berubah dari sebelumnya.
function mon_check_alerts_signal(): void {
    $rows = mon_signal_list(true)['rows']; // true = sertakan semua kategori

    foreach ($rows as $r) {
        if ($r['kategori'] === 'no_data') continue; // data tak cukup, jangan sentuh state

        $simplified = in_array($r['kategori'], ['kritis', 'waspada']) ? $r['kategori'] : 'aman';
        $key        = 'signal:' . $r['device_id'];
        $old        = mon_alert_get_state($key);
        if ($old === $simplified) continue; // tidak berubah, skip

        $rxTxt = $r['rx'] !== null ? number_format($r['rx'], 1) . ' dBm' : '—';

        if ($simplified === 'kritis') {
            $msg = "🔴 *SINYAL KRITIS*\n"
                 . "Pelanggan : {$r['pelanggan']}\n"
                 . "RXPower   : {$rxTxt}\n"
                 . "Area      : {$r['area']}\n"
                 . "⚠️ Berisiko putus, perlu ditindak segera.\n\n"
                 . "🕐 " . mon_alert_waktu();
            kirim_telegram($msg);
        } elseif ($simplified === 'waspada') {
            $msg = "🟡 *SINYAL WASPADA*\n"
                 . "Pelanggan : {$r['pelanggan']}\n"
                 . "RXPower   : {$rxTxt}\n"
                 . "Area      : {$r['area']}\n\n"
                 . "🕐 " . mon_alert_waktu();
            kirim_telegram($msg);
        } elseif ($old === 'kritis' || $old === 'waspada') { // aman, & sebelumnya bermasalah
            $msg = "✅ *Sinyal {$r['pelanggan']} sudah membaik*\nRXPower sekarang: {$rxTxt}\n\n🕐 " . mon_alert_waktu();
            kirim_telegram($msg);
        }

        mon_alert_set_state($key, $simplified, $r['pelanggan']);
    }
}

// Entry point tunggal — dipanggil worker.php sesudah tiap acs:sync.
function mon_check_alerts(): void {
    mon_check_alerts_offline();
    mon_check_alerts_signal();
}
