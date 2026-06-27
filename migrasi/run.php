<?php
// ============================================================
//  KAHFINET - Migration Runner (CLI)
//  Jalankan: php migrasi/run.php
//  Setiap file di folder ini (selain run.php) dieksekusi urut
//  berdasar nama file, dan hanya dijalankan SEKALI — dilacak
//  lewat tabel `migrations`.
// ============================================================

if (PHP_SAPI !== 'cli') {
    die("Script ini cuma boleh dijalankan lewat terminal (CLI): php migrasi/run.php\n");
}

require_once __DIR__ . '/../system/init.php';

db_query("CREATE TABLE IF NOT EXISTS `migrations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`    VARCHAR(255) NOT NULL,
  `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$files = glob(__DIR__ . '/*.php');
sort($files, SORT_STRING);

$dijalankan = 0;
$dilewati   = 0;

foreach ($files as $file) {
    $name = basename($file);
    if ($name === 'run.php') continue;

    $sudah = db_row("SELECT id FROM migrations WHERE filename = ?", [$name]);
    if ($sudah) {
        echo "SKIP  $name (sudah pernah dijalankan)\n";
        $dilewati++;
        continue;
    }

    echo "RUN   $name ... ";
    try {
        require $file;
        db_insert('migrations', ['filename' => $name, 'executed_at' => date('Y-m-d H:i:s')]);
        echo "OK\n";
        $dijalankan++;
    } catch (Throwable $e) {
        echo "GAGAL\n";
        echo "      Error: " . $e->getMessage() . "\n";
        echo "Migrasi dihentikan. Perbaiki dulu file $name sebelum lanjut.\n";
        exit(1);
    }
}

echo "\nSelesai. $dijalankan migrasi baru dijalankan, $dilewati dilewati (sudah pernah jalan).\n";
