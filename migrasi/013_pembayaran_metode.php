<?php
// ============================================================
//  Migrasi 013 — Tambah kolom metode pembayaran (tunai/transfer)
// ============================================================

db_query("
    ALTER TABLE pembayaran
    ADD COLUMN metode ENUM('tunai','transfer') NOT NULL DEFAULT 'tunai'
    AFTER terbayar
");
