<?php
// ============================================================
//  MIGRASI 008 — Tambah footer otomatis SIMPATI ke semua template WA
// ============================================================

$footer = "\n_🤖 Pesan otomatis • SIMPATI oleh {nama_isp}_\n_Jangan balas pesan ini_";

db_query("UPDATE wa_templates SET konten = CONCAT(konten, ?), updated_at = NOW()", [$footer]);
