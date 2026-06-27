<?php
// ============================================================
//  MIGRASI AWAL — import file hasil mysqldump (skema + data).
//  File .sql di sebelah ini (001_migrasi_awal.sql) dieksekusi
//  langsung lewat koneksi PDO terpisah (multi-statement), supaya
//  bisa bawa data yang sudah ada saat pindah ke server baru.
// ============================================================

$sqlFile = __DIR__ . '/001_migrasi_awal.sql';
if (!file_exists($sqlFile)) {
    throw new RuntimeException("File dump tidak ditemukan: $sqlFile");
}

// Database tujuan (DB_NAME di config.php) harus sudah dibuat duluan lewat
// panel hosting/phpMyAdmin/CLI — dump ini tidak mengandung CREATE DATABASE.
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHAR);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);

$sql = file_get_contents($sqlFile);
$pdo->exec($sql);
