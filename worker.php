<?php
// ============================================================
//  SIMPATI — CLI Worker
//  Penggunaan:
//    php worker.php wa:work    → Jalankan antrian WA
//    php worker.php wa:status  → Lihat status antrian
//    php worker.php wa:reset   → Reset job gagal ke pending
// ============================================================

if (PHP_SAPI !== 'cli') {
    exit("Error: worker.php hanya bisa dijalankan via terminal (CLI).\n");
}

require_once __DIR__ . '/system/init.php';

define('WA_MAX_ATTEMPTS', 3);
define('WA_RETRY_DELAYS', [5, 15]); // menit: gagal ke-1 → 5 mnt, gagal ke-2 → 15 mnt

$cmd = $argv[1] ?? 'help';

switch ($cmd) {
    case 'wa:work':   wa_work();   break;
    case 'wa:status': wa_status(); break;
    case 'wa:reset':  wa_reset();  break;
    default:          wa_help();   break;
}

// ── Help ─────────────────────────────────────────────────────
function wa_help(): void {
    echo <<<TXT

  ╔══════════════════════════════════════╗
  ║   SIMPATI Worker — Antrian WA        ║
  ╚══════════════════════════════════════╝

  Penggunaan:
    php worker.php wa:work    Jalankan worker (proses antrian WA)
    php worker.php wa:status  Lihat status antrian
    php worker.php wa:reset   Reset semua job gagal → pending

  Contoh:
    php worker.php wa:work
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
    $n = db_query(
        "UPDATE wa_queue
         SET status = 'pending', attempts = 0, next_retry = NULL, error_msg = NULL
         WHERE status = 'gagal'"
    );
    $jumlah = db_row("SELECT ROW_COUNT() as n")['n'] ?? 0;
    echo "\n  Reset selesai: semua job gagal dikembalikan ke pending.\n\n";
}

// ── Log helper ────────────────────────────────────────────────
function wa_log(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}
