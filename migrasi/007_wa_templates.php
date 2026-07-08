<?php
// ============================================================
//  MIGRASI 007 — Tabel wa_templates + insert template default
// ============================================================

db_query("CREATE TABLE IF NOT EXISTS `wa_templates` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`        VARCHAR(50)  NOT NULL,
  `nama`        VARCHAR(100) NOT NULL,
  `konten`      TEXT         NOT NULL,
  `placeholder` VARCHAR(500) DEFAULT NULL,
  `aktif`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$templates = [
    [
        'kode'        => 'bukti_bayar',
        'nama'        => 'Bukti Pembayaran',
        'konten'      => "*BUKTI PEMBAYARAN IURAN*\n*{nama_isp}*\n{struk}\nTerima kasih sudah membayar! 🙏\n_SIMPATI · Powered by {nama_isp}_",
        'placeholder' => '{nama_isp}, {struk}',
    ],
    [
        'kode'        => 'isolir',
        'nama'        => 'Pemberitahuan Isolir',
        'konten'      => "*{nama_isp} - Pemberitahuan Isolir*\n\nHalo *{nama}* 👋\n\nLayanan internet Anda saat ini *diisolir* karena tagihan bulan *{periode}* belum lunas.\n\n💰 Tagihan: *{jumlah}*\n\nPembayaran bisa transfer ke:\n🏦 *BRI* · 212901000949539\nA.N *Muhamad Yasir*\n\nSetelah bayar, kabari kami ya biar langsung diaktifkan! 🚀\n\n_{nama_isp}_",
        'placeholder' => '{nama_isp}, {nama}, {paket}, {periode}, {jumlah}',
    ],
];

foreach ($templates as $tpl) {
    db_query(
        "INSERT INTO wa_templates (kode, nama, konten, placeholder, aktif)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE nama = VALUES(nama), placeholder = VALUES(placeholder)",
        [$tpl['kode'], $tpl['nama'], $tpl['konten'], $tpl['placeholder']]
    );
}
