<?php
// ============================================================
//  MIGRASI 006 — Ganti role 'kasir' menjadi 'keuangan'
//  1. Tambah 'keuangan' ke ENUM dulu, lalu UPDATE data,
//     lalu hapus 'kasir' dari ENUM.
// ============================================================

// Langkah 1: perluas ENUM supaya menerima kedua nilai
db_query("ALTER TABLE pengguna
    MODIFY COLUMN role ENUM('admin','teknisi','kasir','keuangan') NOT NULL DEFAULT 'keuangan'");

// Langkah 2: pindahkan semua user kasir ke keuangan
db_query("UPDATE pengguna SET role = 'keuangan' WHERE role = 'kasir'");

// Langkah 3: hapus 'kasir' dari ENUM
db_query("ALTER TABLE pengguna
    MODIFY COLUMN role ENUM('admin','teknisi','keuangan') NOT NULL DEFAULT 'keuangan'");
