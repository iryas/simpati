<?php
// ============================================================
//  Migrasi 014 — Kolom PIN portal pelanggan
//  Menambah kredensial login portal pelanggan (No HP + PIN).
//  PIN disimpan ter-hash (password_hash), bukan plaintext.
// ============================================================

db_query("
    ALTER TABLE pelanggan
    ADD COLUMN pin               VARCHAR(255) DEFAULT NULL COMMENT 'Hash PIN login portal pelanggan' AFTER no_hp,
    ADD COLUMN pin_updated_at    DATETIME     DEFAULT NULL COMMENT 'Kapan PIN terakhir diubah'        AFTER pin,
    ADD COLUMN portal_last_login DATETIME     DEFAULT NULL COMMENT 'Login portal terakhir'            AFTER pin_updated_at
");
