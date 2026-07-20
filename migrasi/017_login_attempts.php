<?php
// ============================================================
//  Migrasi 017 — Tabel login_attempts
//  Menyimpan percobaan login gagal & lockout brute-force di DB
//  (bukan $_SESSION), agar tidak bisa di-bypass dengan membuang
//  cookie sesi tiap percobaan. Dipakai login staf & portal.
// ============================================================

db_query("
    CREATE TABLE IF NOT EXISTS login_attempts (
        identifier   VARCHAR(191) NOT NULL PRIMARY KEY,
        attempts     INT UNSIGNED NOT NULL DEFAULT 0,
        locked_until DATETIME DEFAULT NULL,
        updated_at   DATETIME DEFAULT NULL,
        KEY idx_locked (locked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
