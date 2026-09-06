<?php
// ============================================================
//  Migrasi 022 — Tambah kolom fup_diterapkan_at di usage_pppoe_harian
//  Waktu persis pelanggan kena FUP (buat ditampilin di halaman
//  "Kena FUP" — kolom "Kena Sejak"). fup_diterapkan sendiri jadi
//  3 status: 0=normal, 1=FUP aktif otomatis, 2=FUP dicabut manual
//  (dikecualikan dari FUP sisa hari itu, biar nggak langsung
//  ke-trigger ulang di siklus poll berikutnya).
// ============================================================

db_query("
    ALTER TABLE usage_pppoe_harian
    ADD COLUMN fup_diterapkan_at DATETIME DEFAULT NULL AFTER fup_diterapkan
");
