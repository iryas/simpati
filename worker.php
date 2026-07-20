<?php
// ============================================================
//  SIMPATI — CLI Worker
//  Penggunaan:
//    php worker.php wa:work      → Jalankan antrian WA
//    php worker.php wa:status    → Lihat status antrian
//    php worker.php wa:reset     → Reset job gagal ke pending
//    php worker.php usage:poll   → Polling byte-out & uptime sekali (untuk cron)
//    php worker.php usage:work   → Polling terus-menerus (loop, default tiap 1 menit)
//    php worker.php usage:show   → Lihat rekap pemakaian bulan ini
// ============================================================

if (PHP_SAPI !== 'cli') {
    exit("Error: worker.php hanya bisa dijalankan via terminal (CLI).\n");
}

require_once __DIR__ . '/system/init.php';

define('WA_MAX_ATTEMPTS', 3);
define('WA_RETRY_DELAYS', [5, 15]); // menit: gagal ke-1 → 5 mnt, gagal ke-2 → 15 mnt

$cmd = $argv[1] ?? 'help';

switch ($cmd) {
    case 'wa:work':     wa_work();      break;
    case 'wa:status':   wa_status();    break;
    case 'wa:reset':    wa_reset();     break;
    case 'usage:poll':  usage_poll();   break;
    case 'usage:work':  usage_work();   break;
    case 'usage:show':  usage_show();   break;
    default:            wa_help();      break;
}

// ── Usage: polling byte-out & uptime dari Mikrotik ───────────
function usage_poll(): void {
    require_once __DIR__ . '/system/mikrotik.php';

    wa_log("Polling /ppp/active dari Mikrotik...");

    $active = mikrotik_fetch_active();
    if ($active === null) {
        wa_log("✗ Gagal konek ke Mikrotik. Cek pengaturan router.");
        return;
    }
    if (empty($active)) {
        wa_log("Tidak ada sesi PPPoE aktif.");
        return;
    }

    // Counter byte diambil dari /interface (bytes-out tidak tersedia di /ppp/active).
    $traffic = mikrotik_fetch_pppoe_traffic() ?? [];

    // Tentukan bulan_tagihan yang sedang berjalan
    $bulan = bulan_tagihan_sekarang();
    wa_log("Bulan tagihan: $bulan | Sesi aktif: " . count($active) . " | Traffic iface: " . count($traffic));

    // Map nama secret → pelanggan_id dari DB
    $secret_map = [];
    $rows = db_rows(
        "SELECT msc.name, pl.id as pelanggan_id
         FROM mikrotik_secrets_cache msc
         INNER JOIN pelanggan pl ON pl.mikrotik_secrets_id = msc.id
         WHERE pl.status = 'aktif'"
    );
    foreach ($rows as $r) {
        $secret_map[strtolower($r['name'])] = (int)$r['pelanggan_id'];
    }

    $updated = 0;
    $skipped = 0;

    foreach ($active as $sess) {
        $name       = strtolower($sess['name'] ?? '');
        $bytes_out  = (int)($traffic[$name]['tx'] ?? 0);   // tx-byte = download pelanggan
        $uptime_str = $sess['uptime'] ?? '0s';
        $uptime_sec = parse_mikrotik_uptime($uptime_str);

        if (!isset($secret_map[$name])) {
            $skipped++;
            continue;
        }

        $pelanggan_id = $secret_map[$name];

        // Ambil snapshot terakhir
        $existing = db_row(
            "SELECT bytes_out, last_bytes_snapshot, uptime_seconds, last_uptime_snapshot
             FROM usage_pppoe WHERE pelanggan_id = ? AND bulan_tagihan = ?",
            [$pelanggan_id, $bulan]
        );

        if ($existing) {
            // Hitung increment bytes (deteksi reconnect: current < last)
            $last_bytes  = (int)$existing['last_bytes_snapshot'];
            $bytes_inc   = $bytes_out >= $last_bytes
                         ? $bytes_out - $last_bytes
                         : $bytes_out;

            // Hitung increment uptime
            $last_uptime = (int)$existing['last_uptime_snapshot'];
            $uptime_inc  = $uptime_sec >= $last_uptime
                         ? $uptime_sec - $last_uptime
                         : $uptime_sec;

            db_update('usage_pppoe', [
                'bytes_out'            => (int)$existing['bytes_out'] + $bytes_inc,
                'last_bytes_snapshot'  => $bytes_out,
                'uptime_seconds'       => (int)$existing['uptime_seconds'] + $uptime_inc,
                'last_uptime_snapshot' => $uptime_sec,
                'last_poll_at'         => date('Y-m-d H:i:s'),
            ], 'pelanggan_id = ? AND bulan_tagihan = ?', [$pelanggan_id, $bulan]);
        } else {
            db_insert('usage_pppoe', [
                'pelanggan_id'         => $pelanggan_id,
                'bulan_tagihan'        => $bulan,
                'bytes_out'            => $bytes_out,
                'last_bytes_snapshot'  => $bytes_out,
                'uptime_seconds'       => $uptime_sec,
                'last_uptime_snapshot' => $uptime_sec,
                'last_poll_at'         => date('Y-m-d H:i:s'),
            ]);
        }
        $updated++;
    }

    wa_log("✓ Selesai — update: $updated, skip (tidak terdaftar): $skipped");
}

// ── Usage: loop terus-menerus (poll tiap N menit) ────────────
//  Alternatif cron. Contoh: php worker.php usage:work 1  (tiap 1 menit)
function usage_work(): void {
    global $argv;
    $menit = max(1, (int)($argv[2] ?? 1)); // interval menit, default 1
    wa_log("Usage worker mulai — polling tiap {$menit} menit. Tekan Ctrl+C untuk berhenti.\n");
    while (true) {
        usage_poll();
        wa_log("Tidur {$menit} menit sebelum polling berikutnya...\n");
        sleep($menit * 60);
    }
}

// ── Usage: tampilkan rekap bulan ini ─────────────────────────
function usage_show(): void {
    global $argv;
    $bulan = $argv[2] ?? bulan_tagihan_sekarang();
    $rows  = db_rows(
        "SELECT pl.nama, up.bytes_out, up.uptime_seconds, up.last_poll_at
         FROM usage_pppoe up
         INNER JOIN pelanggan pl ON pl.id = up.pelanggan_id
         WHERE up.bulan_tagihan = ?
         ORDER BY up.bytes_out DESC",
        [$bulan]
    );

    echo "\n  ╔══════════════════════════════════════════╗\n";
    echo "  ║   Rekap Pemakaian — Bulan $bulan     ║\n";
    echo "  ╚══════════════════════════════════════════╝\n\n";

    if (!$rows) {
        echo "  Belum ada data untuk bulan $bulan.\n\n";
        return;
    }

    echo sprintf("  %-20s %12s %20s\n", 'Pelanggan', 'Data', 'Online');
    echo "  " . str_repeat('─', 55) . "\n";
    foreach ($rows as $r) {
        echo sprintf("  %-20s %12s %20s\n",
            mb_substr($r['nama'], 0, 20),
            format_bytes((int)$r['bytes_out']),
            format_uptime_seconds((int)$r['uptime_seconds'])
        );
    }
    echo "\n";
}

// ── Help ─────────────────────────────────────────────────────
function wa_help(): void {
    echo <<<TXT

  ╔══════════════════════════════════════╗
  ║        SIMPATI Worker                ║
  ╚══════════════════════════════════════╝

  Antrian WA:
    php worker.php wa:work      Jalankan worker (proses antrian WA)
    php worker.php wa:status    Lihat status antrian
    php worker.php wa:reset     Reset semua job gagal → pending

  Pemakaian PPPoE:
    php worker.php usage:poll     Polling sekali (untuk cron)
    php worker.php usage:work [n] Polling terus-menerus tiap n menit (default 1)
    php worker.php usage:show     Rekap pemakaian bulan ini

  Contoh jalankan polling tiap 5 menit (Windows):
    php worker.php usage:poll
    php worker.php wa:status

TXT;
}

// ── Worker utama ─────────────────────────────────────────────
function wa_work(): void {
    $nama_isp = app_setting('nama_isp', 'SIMPATI');
    wa_log("Worker $nama_isp mulai. Gateway: " . app_setting('wa_gateway', 'wablas'));
    wa_log("Tekan Ctrl+C untuk berhenti.\n");

    // Reset job stuck (processing > 5 menit) ke pending saat startup
    $stuckStmt = db_query(
        "UPDATE wa_queue SET status = 'pending', next_retry = NOW()
         WHERE status = 'processing'
         AND last_attempt < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
    $stuckCount = $stuckStmt->rowCount();
    if ($stuckCount > 0) wa_log("Reset {$stuckCount} job stuck ke pending.");

    while (true) {
        $job = db_row(
            "SELECT * FROM wa_queue
             WHERE status = 'pending'
             AND (next_retry IS NULL OR next_retry <= NOW())
             ORDER BY created_at ASC
             LIMIT 1"
        );

        if (!$job) {
            wa_log("Tidak ada job pending. Cek lagi dalam 30 detik...");
            sleep(30);
            continue;
        }

        // Tandai sedang diproses
        db_update('wa_queue', [
            'status'       => 'processing',
            'last_attempt' => date('Y-m-d H:i:s'),
            'attempts'     => $job['attempts'] + 1,
        ], 'id = ?', [$job['id']]);

        $attempt = $job['attempts'] + 1;
        wa_log("Mengirim WA ke {$job['no_hp']} [{$job['tipe']}] (percobaan {$attempt}/" . WA_MAX_ATTEMPTS . ")...");

        $result = kirim_wa($job['no_hp'], $job['pesan'], (int)($job['pembayaran_id'] ?? 0));

        if ($result['ok']) {
            db_update('wa_queue', [
                'status'  => 'terkirim',
                'sent_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$job['id']]);
            wa_log("✓ Terkirim ke {$job['no_hp']}");
        } else {
            wa_log("✗ Gagal: " . $result['msg']);

            if ($attempt >= WA_MAX_ATTEMPTS) {
                // Gagal permanen
                db_update('wa_queue', [
                    'status'    => 'gagal',
                    'error_msg' => mb_substr($result['msg'], 0, 500),
                ], 'id = ?', [$job['id']]);
                wa_log("✗ GAGAL PERMANEN setelah {$attempt}x percobaan → {$job['no_hp']}");
            } else {
                // Jadwal retry
                $delays      = WA_RETRY_DELAYS;
                $delay_menit = $delays[$attempt - 1] ?? 15;
                $next_retry  = date('Y-m-d H:i:s', time() + ($delay_menit * 60));
                db_update('wa_queue', [
                    'status'     => 'pending',
                    'error_msg'  => mb_substr($result['msg'], 0, 500),
                    'next_retry' => $next_retry,
                ], 'id = ?', [$job['id']]);
                wa_log("↻ Retry ke-{$attempt} dijadwalkan pukul " . date('H:i', strtotime($next_retry)));
            }
        }

        // Jeda random sebelum job berikutnya (hanya kalau ada lagi)
        $next = db_row("SELECT id FROM wa_queue WHERE status='pending' AND (next_retry IS NULL OR next_retry <= NOW()) LIMIT 1");
        if ($next) {
            $jeda = rand(10, 30);
            wa_log("Jeda {$jeda} detik sebelum job berikutnya...\n");
            sleep($jeda);
        }
    }
}

// ── Status antrian ────────────────────────────────────────────
function wa_status(): void {
    $stats = db_rows("SELECT status, COUNT(*) as n FROM wa_queue GROUP BY status ORDER BY FIELD(status,'pending','processing','terkirim','gagal')");

    echo "\n  ╔══════════════════════════════╗\n";
    echo "  ║   Status Antrian WA          ║\n";
    echo "  ╚══════════════════════════════╝\n\n";

    if (!$stats) {
        echo "  Antrian kosong.\n\n";
        return;
    }

    foreach ($stats as $s) {
        $icon = match($s['status']) {
            'pending'    => '⏳',
            'processing' => '🔄',
            'terkirim'   => '✓ ',
            'gagal'      => '✗ ',
            default      => '  ',
        };
        echo sprintf("  %s %-12s : %d\n", $icon, ucfirst($s['status']), $s['n']);
    }

    $gagal = db_rows(
        "SELECT id, no_hp, tipe, attempts, error_msg, created_at
         FROM wa_queue WHERE status = 'gagal'
         ORDER BY created_at DESC LIMIT 10"
    );
    if ($gagal) {
        echo "\n  Job Gagal Permanen:\n";
        echo "  " . str_repeat('─', 50) . "\n";
        foreach ($gagal as $g) {
            echo "  [#{$g['id']}] {$g['no_hp']} ({$g['tipe']}) — {$g['error_msg']}\n";
        }
    }
    echo "\n";
}

// ── Reset job gagal ───────────────────────────────────────────
function wa_reset(): void {
    $stmt = db_query(
        "UPDATE wa_queue
         SET status = 'pending', attempts = 0, next_retry = NULL, error_msg = NULL
         WHERE status = 'gagal'"
    );
    $jumlah = $stmt->rowCount();
    echo "\n  Reset selesai: {$jumlah} job gagal dikembalikan ke pending.\n\n";
}

// ── Log helper ────────────────────────────────────────────────
function wa_log(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}
