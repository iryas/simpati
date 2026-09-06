<?php
// ============================================================
//  Migrasi 021 — Tambah kolom fup_diterapkan di usage_pppoe_harian
//  Penanda: pelanggan ini udah di-throttle FUP hari itu (biar nggak
//  reboot ONT / push profile berkali-kali tiap siklus usage:poll,
//  dan biar cron fup:reset tau siapa yang perlu direset).
// ============================================================

db_query("
    ALTER TABLE usage_pppoe_harian
    ADD COLUMN fup_diterapkan TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER uptime_seconds
");
