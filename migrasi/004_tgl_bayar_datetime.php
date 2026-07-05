<?php
// ============================================================
//  MIGRASI 004 — Ubah tgl_bayar dari DATE ke DATETIME
//  agar jam pembayaran tersimpan dan muncul di bukti WA
// ============================================================

db_query("ALTER TABLE `pembayaran`
    MODIFY COLUMN `tgl_bayar` DATETIME DEFAULT NULL");
