<?php
// ============================================================
//  KAHFINET - Backup Database (dump SQL murni PHP)
//  Tidak pakai mysqldump/exec() — biar jalan di hosting mana pun
//  walau shell_exec() dimatiin. Dump di-stream langsung ke output,
//  bukan ditampung di 1 string besar, biar hemat memory.
// ============================================================

// Cetak dump SQL lengkap (struktur + data semua tabel) ke output buffer
// yang sedang aktif. Caller yang atur header HTTP (Content-Type,
// Content-Disposition) sebelum manggil ini.
function backup_stream_sql(): void {
    $pdo   = db();
    $dbKey = 'Tables_in_' . DB_NAME;
    $tables = array_map(fn($r) => $r[$dbKey], db_rows('SHOW TABLES'));

    echo "-- ============================================================\n";
    echo "-- KAHFINET — Backup Database\n";
    echo "-- Database : " . DB_NAME . "\n";
    echo "-- Dibuat   : " . date('Y-m-d H:i:s') . "\n";
    echo "-- ============================================================\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        backup_dump_table($pdo, $table);
        @ob_flush();
        @flush();
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
}

// Dump 1 tabel: DROP + CREATE (struktur persis dari SHOW CREATE TABLE),
// lalu semua barisnya sebagai INSERT (dikirim per-chunk, bukan 1 query
// raksasa, biar aman buat tabel yang lumayan besar kayak usage_pppoe*).
function backup_dump_table(PDO $pdo, string $table): void {
    echo "-- --------------------------------------------------\n";
    echo "-- Tabel `$table`\n";
    echo "-- --------------------------------------------------\n";

    $create = db_row("SHOW CREATE TABLE `$table`");
    $createSql = $create['Create Table'] ?? null;
    if ($createSql === null) return; // tabel hilang di tengah proses, skip aman

    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $createSql . ";\n\n";

    $total = (int)(db_row("SELECT COUNT(*) c FROM `$table`")['c'] ?? 0);
    if ($total === 0) return;

    $chunkSize = 300;
    $cols      = null;
    $colList   = null;

    for ($offset = 0; $offset < $total; $offset += $chunkSize) {
        $rows = db_rows("SELECT * FROM `$table` LIMIT $chunkSize OFFSET $offset");
        if (!$rows) break;

        if ($cols === null) {
            $cols    = array_keys($rows[0]);
            $colList = '`' . implode('`,`', $cols) . '`';
        }

        $lines = [];
        foreach ($rows as $row) {
            $vals = array_map(
                fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                $row
            );
            $lines[] = '(' . implode(',', $vals) . ')';
        }
        echo "INSERT INTO `$table` ($colList) VALUES\n" . implode(",\n", $lines) . ";\n";
    }
    echo "\n";
}
