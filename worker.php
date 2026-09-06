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
//    php worker.php acs:sync     → Sync cache ONU dari GenieACS sekali (untuk cron)
//    php worker.php acs:work     → Sync terus-menerus (loop, default tiap 1 menit)
//  (Reset FUP tengah malam nempel otomatis di usage:poll/usage:work — nggak
//   perlu command/cron terpisah, lihat fup_reset_jika_hari_baru().)
// ============================================================

if (PHP_SAPI !== 'cli') {
    exit("Error: worker.php hanya bisa dijalankan via terminal (CLI).\n");
}

require_once __DIR__ . '/system/init.php';

define('WA_MAX_ATTEMPTS', 3);
define('WA_RETRY_DELAYS', [5, 15]); // menit: gagal ke-1 → 5 mnt, gagal ke-2 → 15 mnt

$cmd = $argv[1] ?? 'help';

// File log per-perintah, ditulis LANGSUNG oleh wa_log() lewat path absolut
// (__DIR__) — tidak bergantung redirect shell "> file", jadi log tetap terisi
// walau dijalankan langsung via php.exe di Task Scheduler / cron.
$__log_map = ['wa:work' => 'wa_worker.log', 'usage:poll' => 'usage_poll.log', 'usage:work' => 'usage_poll.log', 'acs:sync' => 'acs_sync.log', 'acs:work' => 'acs_sync.log'];
$GLOBALS['WORKER_LOG'] = __DIR__ . '/logs/' . ($__log_map[$cmd] ?? 'worker.log');

switch ($cmd) {
    case 'wa:work':     wa_work();      break;
    case 'wa:status':   wa_status();    break;
    case 'wa:reset':    wa_reset();     break;
    case 'usage:poll':  usage_poll();   break;
    case 'usage:work':  usage_work();   break;
    case 'usage:show':  usage_show();   break;
    case 'acs:sync':    acs_sync_run(); break;
    case 'acs:work':    acs_work();     break;
    default:            wa_help();      break;
}

// ── Usage: polling byte-out & uptime dari Mikrotik ───────────
function usage_poll(): void {
    require_once __DIR__ . '/system/mikrotik.php';

    fup_reset_jika_hari_baru();

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

            $totalHariIni = usage_catat_harian($pelanggan_id, $bytes_inc, $uptime_inc);
            usage_cek_fup($pelanggan_id, $totalHariIni);
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

            // Pertama kali pelanggan ini kepoll bulan ini: seluruh bytes_out/uptime_sec
            // saat ini dianggap increment awal (sama seperti nilai yang di-insert di atas).
            $totalHariIni = usage_catat_harian($pelanggan_id, $bytes_out, $uptime_sec);
            usage_cek_fup($pelanggan_id, $totalHariIni);
        }
        $updated++;
    }

    wa_log("✓ Selesai — update: $updated, skip (tidak terdaftar): $skipped");
}

// Catat increment bytes & uptime dari satu poll ke baris TANGGAL HARI INI
// di usage_pppoe_harian (dasar buat modal "Detail Pemakaian Harian" & FUP).
// Delta yang sama dengan yang ditambahkan ke usage_pppoe (per bulan), cuma
// dikreditkan ke tanggal poll berjalan — bukan didistribusikan mundur kalau
// pollingnya lewat tengah malam (pendekatan yang sama seperti counter bulanan).
// Return: total bytes_out TERBARU hari ini (dipakai buat cek kuota FUP).
function usage_catat_harian(int $pelanggan_id, int $bytes_inc, int $uptime_inc): int {
    $tanggal = date('Y-m-d');

    $existing = db_row(
        "SELECT bytes_out, uptime_seconds FROM usage_pppoe_harian
         WHERE pelanggan_id = ? AND tanggal = ?",
        [$pelanggan_id, $tanggal]
    );

    if ($existing) {
        $totalBaru = (int)$existing['bytes_out'] + $bytes_inc;
        db_update('usage_pppoe_harian', [
            'bytes_out'      => $totalBaru,
            'uptime_seconds' => (int)$existing['uptime_seconds'] + $uptime_inc,
            'last_poll_at'   => date('Y-m-d H:i:s'),
        ], 'pelanggan_id = ? AND tanggal = ?', [$pelanggan_id, $tanggal]);
    } else {
        $totalBaru = $bytes_inc;
        db_insert('usage_pppoe_harian', [
            'pelanggan_id'   => $pelanggan_id,
            'tanggal'        => $tanggal,
            'bytes_out'      => $bytes_inc,
            'uptime_seconds' => $uptime_inc,
            'last_poll_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    return $totalBaru;
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

// ── ACS: sync cache ONU dari GenieACS (sekali, untuk cron) ───
function acs_sync_run(): void {
    wa_log("Sync device ONU dari GenieACS (NBI)...");
    $res = acs_sync_devices();
    wa_log(($res['ok'] ? '✓ ' : '✗ ') . $res['msg']);

    // Cek perubahan status ONU offline & sinyal, kirim notifikasi Telegram
    // kalau ada perubahan. Hanya jalan kalau sync-nya sendiri berhasil
    // (cache basi tidak perlu dicek — bisa memicu alert palsu).
    if ($res['ok']) {
        require_once __DIR__ . '/monitoring/_data.php';
        try {
            mon_check_alerts();
        } catch (\Throwable $e) {
            wa_log("✗ Alert check error: " . $e->getMessage());
        }
    }
}

// ── ACS: loop sync terus-menerus (default tiap 1 menit) ──────
//  Contoh: php worker.php acs:work 2  (sync tiap 2 menit)
function acs_work(): void {
    global $argv;
    $menit = max(1, (int)($argv[2] ?? 1)); // interval menit, default 1
    wa_log("ACS sync worker mulai — sync tiap {$menit} menit. Tekan Ctrl+C untuk berhenti.\n");
    while (true) {
        acs_sync_run();
        wa_log("Tidur {$menit} menit sebelum sync berikutnya...\n");
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

  Sync ONU (GenieACS):
    php worker.php acs:sync       Sync cache ONU sekali (untuk cron/Task Scheduler)
    php worker.php acs:work [n]   Sync terus-menerus tiap n menit (default 1)

  Contoh (Windows Task Scheduler tiap 1 menit):
    php worker.php acs:sync
    php worker.php usage:poll

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
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    @file_put_contents($GLOBALS['WORKER_LOG'] ?? (__DIR__ . '/logs/worker.log'), $line, FILE_APPEND);
}
