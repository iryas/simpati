<?php
// ============================================================
//  MIGRASI 009 — Fonnte WA Gateway + nomor CS
// ============================================================

// Tambah setting gateway, token Fonnte, dan no_cs
$settings = [
    'wa_gateway'   => 'wablas',   // default tetap wablas agar tidak breaking
    'fonnte_token' => '',
    'no_cs'        => '',
];

foreach ($settings as $key => $val) {
    db_query(
        "INSERT INTO app_settings (setting_key, setting_val)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE updated_at = NOW()",
        [$key, $val]
    );
}

// Update template isolir: ganti baris konfirmasi agar pakai {no_cs}
// Hanya update jika belum mengandung {no_cs}
$row = db_row("SELECT id, konten FROM wa_templates WHERE kode = 'isolir' LIMIT 1");
if ($row && strpos($row['konten'], '{no_cs}') === false) {
    $lama = "Setelah bayar, kabari kami ya biar langsung diaktifkan!";
    $baru = "Setelah transfer, silakan konfirmasi pembayaran ke:\n📱 *{no_cs}*";
    $konten_baru = str_replace($lama, $baru, $row['konten']);
    if ($konten_baru !== $row['konten']) {
        db_query("UPDATE wa_templates SET konten = ?, updated_at = NOW() WHERE id = ?", [$konten_baru, $row['id']]);
    }
}

// Update placeholder isolir agar mencantumkan no_cs
db_query(
    "UPDATE wa_templates SET placeholder = CONCAT(placeholder, ',{no_cs}'), updated_at = NOW()
     WHERE kode = 'isolir' AND placeholder NOT LIKE '%{no_cs}%'"
);
