<?php
// ============================================================
//  KAHFINET - Cron: Auto-isolir Pelanggan Menunggak
//  Jalankan via CLI: php cron/isolir_check.php
//  Jadwalkan lewat crontab server, misal jam 01:00 setiap hari:
//    0 1 * * * php /path/ke/kahfinet1/cron/isolir_check.php >> /path/ke/kahfinet1/logs/isolir_cron.log 2>&1
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan via CLI.\n");
}

require_once __DIR__ . '/../system/init.php';

function isolir_log(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    @file_put_contents(__DIR__ . '/../logs/isolir_cron.log', $line, FILE_APPEND);
}

isolir_log('Mulai pengecekan pelanggan menunggak.');

$overdue = db_rows(
    "SELECT DISTINCT pl.id, pl.nama, msc.ros_id
     FROM pelanggan pl
     JOIN pembayaran pb ON pb.pelanggan_id = pl.id
     LEFT JOIN mikrotik_secrets_cache msc ON msc.id = pl.mikrotik_secrets_id
     WHERE pl.status = 'aktif'
       AND pb.status = 'belum'
       AND pb.tgl_jatuh_tempo IS NOT NULL
       AND pb.tgl_jatuh_tempo < CURDATE()"
);

if (!$overdue) {
    isolir_log('Tidak ada pelanggan yang perlu diisolir.');
    exit(0);
}

$success = 0;
$failed  = 0;

foreach ($overdue as $row) {
    db_update('pelanggan', ['status' => 'isolir'], 'id = ?', [$row['id']]);

    if (!empty($row['ros_id'])) {
        $ok = mikrotik_secret_set_disabled($row['ros_id'], true);
        if ($ok) {
            $success++;
            isolir_log("Isolir: {$row['nama']} (ID {$row['id']}) — PPP secret di-disable.");
        } else {
            $failed++;
            isolir_log("Isolir: {$row['nama']} (ID {$row['id']}) — status diubah, TAPI gagal disable PPP secret di Mikrotik.");
        }
    } else {
        $success++;
        isolir_log("Isolir: {$row['nama']} (ID {$row['id']}) — status diubah (belum punya PPP secret terdaftar).");
    }
}

isolir_log("Selesai. Total diproses: " . count($overdue) . ", berhasil sync: $success, gagal sync: $failed.");
