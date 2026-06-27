<?php
// ============================================================
//  MIGRASI AWAL — import file hasil mysqldump (skema + data).
//  File .sql di sebelah ini (001_migrasi_awal.sql) dieksekusi
//  statement per statement (bukan 1 query raksasa), supaya tidak
//  kebentur `max_allowed_packet` di server yang settingnya kecil.
// ============================================================

$sqlFile = __DIR__ . '/001_migrasi_awal.sql';
if (!file_exists($sqlFile)) {
    throw new RuntimeException("File dump tidak ditemukan: $sqlFile");
}

// Database tujuan (DB_NAME di config.php) harus sudah dibuat duluan lewat
// panel hosting/phpMyAdmin/CLI — dump ini tidak mengandung CREATE DATABASE.
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHAR);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// mysqldump selalu nutup tiap statement dengan ";" di akhir baris, jadi
// aman dipecah per baris yang diakhiri ";" (dump ini tidak mengandung
// stored procedure/trigger dengan DELIMITER custom).
$sql        = file_get_contents($sqlFile);
$statements = preg_split('/;\s*\n/', $sql);

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || str_starts_with($stmt, '--')) continue;
    $pdo->exec($stmt);
}
