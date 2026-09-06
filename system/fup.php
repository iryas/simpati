<?php
// ============================================================
//  KAHFINET - FUP (Fair Usage Policy)
//  Dipakai dari worker.php (cron usage:poll/usage:work, otomatis)
//  MAUPUN dari halaman admin (tombol "Cabut FUP" manual) — makanya
//  di file library ini, bukan di worker.php yang CLI-only.
// ============================================================

function fup_log(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(__DIR__ . '/../logs/fup.log', $line, FILE_APPEND);
}

// Baca setting FUP LANGSUNG dari DB, bukan lewat app_setting() yang di-cache
// per-proses. Ini sengaja — usage:work jalan sebagai 1 proses PHP yang loop
// terus-menerus (bisa berhari-hari), dan kalau pakai cache, toggle FUP di
// halaman Pengaturan nggak akan kepakai sampai prosesnya di-restart manual.
// Krusial buat kasus darurat (admin butuh matiin FUP instan tanpa restart
// worker di server).
function fup_setting(string $key, string $default = ''): string {
    $row = db_row("SELECT setting_val FROM app_settings WHERE setting_key = ?", [$key]);
    return $row ? (string)$row['setting_val'] : $default;
}

// ── FUP: throttle pelanggan yang lewat kuota harian ──────────
// Dicek tiap siklus usage:poll, langsung setelah usage_catat_harian().
// Kalau total pemakaian hari ini > kuota_harian_gb: push profile Mikrotik
// pelanggan ke mikrotik_profile_fup, lalu reboot ONT-nya (biar profile baru
// langsung kepake — sesi PPPoE yang udah connect nggak otomatis kebaca
// profile baru sampai reconnect, dan ONT sering bengong kalau cuma
// diputus sesi tanpa reboot fisik).
function usage_cek_fup(int $pelanggan_id, int $totalBytesHariIni): void {
    if (fup_setting('fup_aktif', '0') !== '1') return; // FUP dimatiin dari Pengaturan

    $kuotaGb = (float)fup_setting('kuota_harian_gb', '7');
    if ($kuotaGb <= 0) return; // jaga-jaga tambahan kalau kuota di-set 0/kosong

    $kuotaBytes = $kuotaGb * 1024 * 1024 * 1024;
    if ($totalBytesHariIni <= $kuotaBytes) return; // belum lewat kuota

    $tanggal = date('Y-m-d');

    // fup_diterapkan: 0=normal, 1=FUP aktif otomatis, 2=FUP dicabut manual
    // admin (dikecualikan sisa hari ini). Selain 0, jangan diapa-apain lagi —
    // baik yang udah kena (jangan reboot ulang tiap siklus) maupun yang udah
    // dicabut manual (jangan langsung ke-throttle ulang di siklus berikutnya).
    $row = db_row(
        "SELECT fup_diterapkan FROM usage_pppoe_harian WHERE pelanggan_id = ? AND tanggal = ?",
        [$pelanggan_id, $tanggal]
    );
    if (!$row || (int)$row['fup_diterapkan'] !== 0) return;

    // Pelanggan isolir/nonaktif dilewatin — biarin mekanisme isolir yang urus,
    // FUP jangan ikut campur ubah profile mereka.
    $pelanggan = db_row(
        "SELECT p.status, s.ros_id, s.name AS secret_name
         FROM pelanggan p
         LEFT JOIN mikrotik_secrets_cache s ON s.id = p.mikrotik_secrets_id
         WHERE p.id = ?",
        [$pelanggan_id]
    );
    if (!$pelanggan || $pelanggan['status'] !== 'aktif') return;
    if (empty($pelanggan['ros_id'])) {
        fup_log("FUP: pelanggan ID $pelanggan_id lewat kuota tapi belum punya PPP secret terdaftar, dilewatin.");
        return;
    }

    $profilFup = fup_setting('mikrotik_profile_fup', 'profile-FUP');
    $ok = mikrotik_secret_push_profile($pelanggan['ros_id'], $profilFup, false, $pelanggan['secret_name'] ?? '');

    if (!$ok) {
        fup_log("FUP: GAGAL push profile '$profilFup' ke pelanggan ID $pelanggan_id.");
        return;
    }

    db_update('usage_pppoe_harian', [
        'fup_diterapkan'    => 1,
        'fup_diterapkan_at' => date('Y-m-d H:i:s'),
    ], 'pelanggan_id = ? AND tanggal = ?', [$pelanggan_id, $tanggal]);
    fup_log("FUP: pelanggan ID $pelanggan_id lewat kuota (" . round($totalBytesHariIni / 1073741824, 2) . " GB) — profile di-push ke '$profilFup'.");

    $deviceId = fup_cari_device_id($pelanggan['secret_name'] ?? '');
    if ($deviceId) {
        $rebootOk = acs_reboot_device($deviceId);
        fup_log("FUP: reboot ONT pelanggan ID $pelanggan_id (device $deviceId) — " . ($rebootOk ? 'terkirim' : 'GAGAL'));
    } else {
        fup_log("FUP: ONT pelanggan ID $pelanggan_id tidak ketemu di GenieACS, reboot dilewatin.");
    }
}

// Cari device_id GenieACS dari nama secret PPPoE (pola sama kayak yang
// dipakai monitoring/_data.php mon_onu_list(): match pppoe_username ke
// nama secret Mikrotik, case-insensitive).
function fup_cari_device_id(string $secretName): ?string {
    if ($secretName === '') return null;
    $row = db_row(
        "SELECT device_id FROM genieacs_devices_cache WHERE LOWER(pppoe_username) = LOWER(?) LIMIT 1",
        [$secretName]
    );
    return $row['device_id'] ?? null;
}

// Jalanin fup_reset() PALING SEKALI per hari kalender, nempel otomatis di
// usage_poll() — nggak perlu command/cron terpisah kayak isolir_check.php.
// Ditandai lewat app_settings.fup_reset_terakhir, dibaca LANGSUNG dari DB
// (bukan lewat app_setting() yang di-cache) karena usage:work jalan sebagai
// 1 proses PHP yang loop terus — kalau pakai cache, tanggalnya bakal basi
// sepanjang proses itu hidup dan fup_reset() bisa keulang terus tiap siklus.
function fup_reset_jika_hari_baru(): void {
    $hariIni  = date('Y-m-d');
    $terakhir = db_row("SELECT setting_val FROM app_settings WHERE setting_key = 'fup_reset_terakhir'")['setting_val'] ?? '';
    if ($terakhir === $hariIni) return; // udah pernah jalan hari ini

    fup_reset();

    db_query(
        "INSERT INTO app_settings (setting_key, setting_val) VALUES ('fup_reset_terakhir', ?)
         ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), updated_at = NOW()",
        [$hariIni]
    );
}

// FUP: reset pelanggan yang kena throttle KEMARIN (fup_diterapkan=1 —
// yang udah dicabut manual/status 2 dilewatin, udah beres duluan), balik
// ke profile normal sesuai paket mereka saat ini. Dipanggil dari
// fup_reset_jika_hari_baru().
function fup_reset(): void {
    $kemarin = date('Y-m-d', strtotime('-1 day'));

    $daftar = db_rows(
        "SELECT DISTINCT pelanggan_id FROM usage_pppoe_harian
         WHERE tanggal = ? AND fup_diterapkan = 1",
        [$kemarin]
    );

    if (!$daftar) {
        fup_log('FUP reset: tidak ada pelanggan yang perlu direset.');
        return;
    }

    $success = 0;
    $failed  = 0;

    foreach ($daftar as $row) {
        $pid = (int)$row['pelanggan_id'];

        $pelanggan = db_row(
            "SELECT p.status, s.ros_id, s.name AS secret_name, mpc.name AS profil_normal
             FROM pelanggan p
             LEFT JOIN mikrotik_secrets_cache s ON s.id = p.mikrotik_secrets_id
             LEFT JOIN paket pk ON pk.id = p.paket_id
             LEFT JOIN mikrotik_profiles_cache mpc ON mpc.id = pk.mikrotik_profiles_id
             WHERE p.id = ?",
            [$pid]
        );

        if (!$pelanggan || $pelanggan['status'] !== 'aktif') {
            fup_log("FUP reset: pelanggan ID $pid dilewatin (status bukan aktif — kemungkinan udah isolir).");
            continue;
        }
        if (empty($pelanggan['ros_id']) || empty($pelanggan['profil_normal'])) {
            fup_log("FUP reset: pelanggan ID $pid dilewatin (secret/profile paket tidak lengkap).");
            $failed++;
            continue;
        }

        $ok = mikrotik_secret_push_profile($pelanggan['ros_id'], $pelanggan['profil_normal'], false, $pelanggan['secret_name'] ?? '');
        if (!$ok) {
            fup_log("FUP reset: GAGAL push profile normal ke pelanggan ID $pid.");
            $failed++;
            continue;
        }

        $success++;
        fup_log("FUP reset: pelanggan ID $pid balik ke profile '{$pelanggan['profil_normal']}'.");

        $deviceId = fup_cari_device_id($pelanggan['secret_name'] ?? '');
        if ($deviceId) {
            $rebootOk = acs_reboot_device($deviceId);
            fup_log("FUP reset: reboot ONT pelanggan ID $pid (device $deviceId) — " . ($rebootOk ? 'terkirim' : 'GAGAL'));
        }
    }

    fup_log("FUP reset selesai — total: " . count($daftar) . ", berhasil: $success, gagal: $failed.");
}

// ── FUP: cabut manual dari halaman admin ─────────────────────
// Dipanggil dari tombol "Cabut FUP" di tab Kena FUP. Cuma boleh buat
// pelanggan yang BENERAN lagi fup_diterapkan=1 hari ini (bukan 0/2).
// Set status jadi 2 (dicabut manual) — beda dari 0, biar nggak langsung
// ke-throttle ulang di siklus poll berikutnya (pemakaian hari itu kan
// masih di atas kuota).
function fup_cabut_manual(int $pelanggan_id): array {
    $tanggal = date('Y-m-d');

    $row = db_row(
        "SELECT fup_diterapkan FROM usage_pppoe_harian WHERE pelanggan_id = ? AND tanggal = ?",
        [$pelanggan_id, $tanggal]
    );
    if (!$row || (int)$row['fup_diterapkan'] !== 1) {
        return ['ok' => false, 'msg' => 'Pelanggan ini sedang tidak dalam status FUP aktif.'];
    }

    $pelanggan = db_row(
        "SELECT p.nama, s.ros_id, s.name AS secret_name, mpc.name AS profil_normal
         FROM pelanggan p
         LEFT JOIN mikrotik_secrets_cache s ON s.id = p.mikrotik_secrets_id
         LEFT JOIN paket pk ON pk.id = p.paket_id
         LEFT JOIN mikrotik_profiles_cache mpc ON mpc.id = pk.mikrotik_profiles_id
         WHERE p.id = ?",
        [$pelanggan_id]
    );
    if (!$pelanggan) {
        return ['ok' => false, 'msg' => 'Pelanggan tidak ditemukan.'];
    }
    if (empty($pelanggan['ros_id']) || empty($pelanggan['profil_normal'])) {
        return ['ok' => false, 'msg' => 'Data secret Mikrotik / profile paket pelanggan ini tidak lengkap.'];
    }

    $ok = mikrotik_secret_push_profile($pelanggan['ros_id'], $pelanggan['profil_normal'], false, $pelanggan['secret_name'] ?? '');
    if (!$ok) {
        fup_log("Cabut manual GAGAL: pelanggan ID $pelanggan_id ({$pelanggan['nama']}) — push profile normal gagal.");
        return ['ok' => false, 'msg' => 'Gagal push profile ke Mikrotik. Coba lagi, atau cek koneksi router.'];
    }

    db_update('usage_pppoe_harian', ['fup_diterapkan' => 2], 'pelanggan_id = ? AND tanggal = ?', [$pelanggan_id, $tanggal]);
    fup_log("Cabut manual: pelanggan ID $pelanggan_id ({$pelanggan['nama']}) balik ke profile '{$pelanggan['profil_normal']}'.");

    $deviceId = fup_cari_device_id($pelanggan['secret_name'] ?? '');
    $rebootOk = false;
    if ($deviceId) {
        $rebootOk = acs_reboot_device($deviceId);
        fup_log("Cabut manual: reboot ONT pelanggan ID $pelanggan_id (device $deviceId) — " . ($rebootOk ? 'terkirim' : 'GAGAL'));
    }

    if (!$deviceId) {
        return ['ok' => true, 'msg' => "Profile normal terkirim untuk {$pelanggan['nama']}. ONT tidak ketemu di GenieACS, reboot dilewatin — mungkin belum kepake sampai reconnect sendiri."];
    }
    if (!$rebootOk) {
        return ['ok' => true, 'msg' => "Profile normal terkirim untuk {$pelanggan['nama']}, TAPI reboot ONT gagal terkirim. Cek manual kalau perlu."];
    }
    return ['ok' => true, 'msg' => "FUP dicabut untuk {$pelanggan['nama']} — profile normal & reboot ONT terkirim."];
}
