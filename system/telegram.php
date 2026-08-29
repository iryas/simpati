<?php
// ============================================================
//  KAHFINET - Wrapper Notifikasi Telegram (Bot API)
//  Dipakai untuk notifikasi INTERNAL admin/teknisi (bukan pelanggan
//  — jalur pelanggan tetap lewat WhatsApp/system/acs.php terpisah).
//  Tidak pernah melempar exception ke caller. Kegagalan dicatat ke
//  logs/telegram.log dan dikembalikan sebagai false.
// ============================================================

function telegram_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(__DIR__ . '/../logs/telegram.log', $line, FILE_APPEND);
}

// Kirim pesan teks (Markdown) ke bot/grup yang dikonfigurasi di Pengaturan.
function kirim_telegram(string $pesan): bool
{
    $aktif  = app_setting('telegram_aktif', '0');
    $token  = app_setting('telegram_bot_token', '');
    $chatId = app_setting('telegram_chat_id', '');

    if ($aktif !== '1') {
        telegram_log('Dilewati: notifikasi Telegram belum diaktifkan.');
        return false;
    }
    if ($token === '' || $chatId === '') {
        telegram_log('Dilewati: token/chat ID belum diisi.');
        return false;
    }

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => $chatId,
            'text'       => $pesan,
            'parse_mode' => 'Markdown',
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        telegram_log("Gagal konek: $error");
        return false;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        telegram_log("HTTP $httpCode: " . substr($response, 0, 300));
        return false;
    }
    return true;
}

// Tes koneksi bot (dipakai tombol "Tes Kirim" di halaman Pengaturan).
// Beda dari kirim_telegram(): mengembalikan pesan error asli dari Telegram
// (bukan cuma true/false) dan tidak dihalangi toggle "aktif".
function telegram_test(string $token, string $chatId): array
{
    if ($token === '' || $chatId === '') {
        return ['ok' => false, 'msg' => 'Token dan Chat ID wajib diisi dulu.'];
    }

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => $chatId,
            'text'       => "✅ *Tes Notifikasi SIMPATI*\nKalau pesan ini muncul, koneksi bot Telegram sudah benar.",
            'parse_mode' => 'Markdown',
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'msg' => "Gagal konek ke Telegram: $error"];
    }
    $decoded = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300 || empty($decoded['ok'])) {
        $desc = $decoded['description'] ?? substr($response, 0, 200);
        return ['ok' => false, 'msg' => "Telegram menolak: $desc"];
    }
    return ['ok' => true, 'msg' => 'Pesan tes berhasil dikirim! Cek chat/grup Telegram-nya.'];
}
