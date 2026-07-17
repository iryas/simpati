<?php
// ============================================================
//  KAHFINET - Helper Functions (Diperkuat)
// ============================================================

// ── Usage PPPoE Helpers ───────────────────────────────────────
function parse_mikrotik_uptime(string $uptime): int {
    preg_match('/(?:(\d+)w)?(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/', $uptime, $m);
    return ((int)($m[1] ?? 0) * 604800)
         + ((int)($m[2] ?? 0) * 86400)
         + ((int)($m[3] ?? 0) * 3600)
         + ((int)($m[4] ?? 0) * 60)
         + (int)($m[5] ?? 0);
}

function format_bytes(int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function format_uptime_seconds(int $seconds): string {
    $hari  = intdiv($seconds, 86400);
    $jam   = intdiv($seconds % 86400, 3600);
    $menit = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($hari)  $parts[] = $hari . ' hari';
    if ($jam)   $parts[] = $jam . ' jam';
    if ($menit && !$hari) $parts[] = $menit . ' menit';
    return $parts ? implode(' ', $parts) : '< 1 menit';
}

// Tentukan bulan_tagihan yang sedang berjalan berdasarkan tgl_mulai
function bulan_tagihan_sekarang(): string {
    $tgl_mulai = (int)app_setting('tgl_mulai_tagihan', '1');
    if ((int)date('j') >= $tgl_mulai) {
        return date('Y-m');
    }
    return date('Y-m', strtotime('-1 month'));
}

// ── Label Periode Tagihan ─────────────────────────────────────
// Hitung periode "bayar dulu baru pakai" dari bulan_tagihan (Y-m) + tgl_mulai.
// Hasil: "20 Jul – 19 Agu 2026" atau "20 Des 2025 – 19 Jan 2026"
function label_periode_tagihan(string $bulan_ym, int $tgl_mulai): string {
    static $bln_indo = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ts   = strtotime($bulan_ym . '-01');
    $bln  = (int)date('n', $ts);
    $thn  = (int)date('Y', $ts);
    $ts_s = mktime(0, 0, 0, $bln, $tgl_mulai, $thn);
    $ts_e = mktime(0, 0, 0, $bln + 1, $tgl_mulai - 1, $thn);
    $tgl_e = (int)date('j', $ts_e);
    $yr_s  = date('Y', $ts_s);
    $yr_e  = date('Y', $ts_e);
    $left  = $tgl_mulai . ' ' . $bln_indo[(int)date('n', $ts_s)];
    $right = $tgl_e . ' ' . $bln_indo[(int)date('n', $ts_e)] . ' ' . $yr_e;
    if ($yr_s !== $yr_e) $left .= ' ' . $yr_s;
    return $left . ' – ' . $right;
}

// ── App Settings ─────────────────────────────────────────────
function app_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!array_key_exists($key, $cache)) {
        $row = db_row("SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1", [$key]);
        $cache[$key] = $row ? (string)$row['setting_val'] : $default;
    }
    return $cache[$key];
}

// ── Redirect ─────────────────────────────────────────────────
function redirect(string $url): never {
    // Cegah open redirect: hanya izinkan URL yang berawalan BASE_URL
    if (!str_starts_with($url, BASE_URL) && !str_starts_with($url, '/')) {
        $url = BASE_URL . 'index.php';
    }
    header("Location: $url");
    exit;
}

// ── Flash Message ─────────────────────────────────────────────
function flash(string $type, string $msg): void {
    $allowed = ['success', 'danger', 'warning', 'info'];
    if (!in_array($type, $allowed, true)) $type = 'info';
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function render_flash(): void {
    $f = get_flash();
    if (!$f) return;
    // toastr tidak mengenal tipe "danger", map ke "error"
    $type = $f['type'] === 'danger' ? 'error' : $f['type'];
    echo '<script>window.__flash = ' . json_encode(['type' => $type, 'msg' => $f['msg']], JSON_UNESCAPED_UNICODE) . ';</script>';
}

// ── Format Rupiah ─────────────────────────────────────────────
function rupiah(int|float $angka): string {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// ── Format Tanggal ────────────────────────────────────────────
function tgl_indo(string $date, bool $withTime = false): string {
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun',
              'Jul','Agt','Sep','Okt','Nov','Des'];
    $ts  = strtotime($date);
    if ($ts === false) return '—';
    $str = date('d', $ts) . ' ' . $bulan[(int)date('m', $ts)] . ' ' . date('Y', $ts);
    if ($withTime) $str .= ' ' . date('H:i', $ts);
    return $str;
}

// ── Badge Status ──────────────────────────────────────────────
// $bulan_tagihan (format 'Y-m') dipakai untuk membedakan "Belum Lunas" vs "Tunggakan"
function badge_status(string $status, ?string $bulan_tagihan = null): string {
    if ($status === 'belum') {
        if ($bulan_tagihan && $bulan_tagihan < date('Y-m')) {
            return '<span class="badge badge-warning">Tunggakan</span>';
        }
        return '<span class="badge badge-danger">Belum Lunas</span>';
    }
    $map = [
        'aktif'    => ['success',   'Aktif'],
        'nonaktif' => ['secondary', 'Nonaktif'],
        'isolir'   => ['warning',   'Isolir'],
        'lunas'    => ['success',   'Lunas'],
        'pending'  => ['info',      'Pending'],
    ];
    [$color, $label] = $map[strtolower($status)] ?? ['secondary', ucfirst($status)];
    return '<span class="badge badge-' . $color . '">' . clean($label) . '</span>';
}

// ── Badge Role ────────────────────────────────────────────────
function badge_role(string $role): string {
    $map = [
        'admin'   => 'danger',
        'teknisi' => 'primary',
        'keuangan' => 'success',
    ];
    $color = $map[$role] ?? 'secondary';
    return '<span class="badge badge-' . $color . '">' . clean(ucfirst($role)) . '</span>';
}

// ── Sanitize ──────────────────────────────────────────────────
function clean(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// ── Validasi & Sanitasi Input ─────────────────────────────────
function validate_input(array $rules, array $data): array {
    $errors = [];
    foreach ($rules as $field => $rule) {
        $value = trim($data[$field] ?? '');

        if (isset($rule['required']) && $rule['required'] && $value === '') {
            $errors[$field] = ($rule['label'] ?? $field) . ' wajib diisi.';
            continue;
        }
        if ($value === '') continue;

        if (isset($rule['max']) && mb_strlen($value) > $rule['max']) {
            $errors[$field] = ($rule['label'] ?? $field) . ' maksimal ' . $rule['max'] . ' karakter.';
        }
        if (isset($rule['min']) && mb_strlen($value) < $rule['min']) {
            $errors[$field] = ($rule['label'] ?? $field) . ' minimal ' . $rule['min'] . ' karakter.';
        }
        if (isset($rule['numeric']) && $rule['numeric'] && !is_numeric($value)) {
            $errors[$field] = ($rule['label'] ?? $field) . ' harus berupa angka.';
        }
        if (isset($rule['regex']) && !preg_match($rule['regex'], $value)) {
            $errors[$field] = ($rule['label'] ?? $field) . ' format tidak valid.';
        }
    }
    return $errors;
}

// ── CSRF ─────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): void {
    echo '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): bool {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ── Pagination ────────────────────────────────────────────────
function paginate(string $sql, array $params, int $perPage, int $page): array {
    $countSql = "SELECT COUNT(*) as total FROM ($sql) as sub";
    $total    = (int) db_row($countSql, $params)['total'];
    $offset   = ($page - 1) * $perPage;
    $rows     = db_rows("$sql LIMIT $perPage OFFSET $offset", $params);
    return [
        'rows'      => $rows,
        'total'     => $total,
        'per_page'  => $perPage,
        'page'      => $page,
        'last_page' => (int) ceil($total / $perPage),
    ];
}

function render_pagination(array $pager, string $url): void {
    if ($pager['last_page'] <= 1) return;
    $p    = $pager['page'];
    $last = $pager['last_page'];
    // Sanitasi URL
    $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<nav><ul class="pagination pagination-sm justify-content-end mb-0">';
    echo '<li class="page-item ' . ($p <= 1 ? 'disabled' : '') . '">
            <a class="page-link" href="' . $url . '&page=' . ($p - 1) . '">&laquo;</a></li>';
    for ($i = max(1, $p - 2); $i <= min($last, $p + 2); $i++) {
        echo '<li class="page-item ' . ($i === $p ? 'active' : '') . '">
                <a class="page-link" href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
    }
    echo '<li class="page-item ' . ($p >= $last ? 'disabled' : '') . '">
            <a class="page-link" href="' . $url . '&page=' . ($p + 1) . '">&raquo;</a></li>';
    echo '</ul></nav>';
}

// ── Generate Token ────────────────────────────────────────────
function generate_token(int $length = 64): string {
    return bin2hex(random_bytes($length / 2));
}

// ── Format Nomor HP ke Format WA (62xxx) ─────────────────────
function format_no_hp_wa(string $no_hp): string {
    $no = preg_replace('/[^0-9]/', '', $no_hp);
    if (str_starts_with($no, '0')) {
        $no = '62' . substr($no, 1);
    } elseif (!str_starts_with($no, '62')) {
        $no = '62' . $no;
    }
    return $no;
}

// ── WA Template: ambil konten dari DB ────────────────────────
function wa_template(string $kode): string {
    static $cache = [];
    if (!array_key_exists($kode, $cache)) {
        $row = db_row("SELECT konten FROM wa_templates WHERE kode = ? AND aktif = 1", [$kode]);
        $cache[$kode] = $row ? $row['konten'] : '';
    }
    return $cache[$kode];
}

// ── WA Template: render dengan replace placeholder {key} ─────
function wa_render(string $kode, array $vars): string {
    $tpl = wa_template($kode);
    if (!$tpl) return '';
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{' . $k . '}', (string)$v, $tpl);
    }
    return $tpl;
}

// ── Format Teks Pesan Bukti Pembayaran untuk WA ───────────────
// $row harus mengandung: id, jumlah, potongan, terbayar, tgl_bayar,
//   bulan_tagihan, nama_pelanggan, no_hp, nama_paket
function format_pesan_bukti_bayar(array $row): string {
    $nama_isp    = app_setting('nama_isp', 'KahfiNet');
    $jumlah      = (int)$row['jumlah'];
    $potongan    = (int)$row['potongan'];
    $terbayar    = (int)$row['terbayar'];
    $nominal_pot = $jumlah - $terbayar;

    $bln_indo  = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $ts_bayar  = strtotime($row['tgl_bayar'] ?? 'now');
    $tgl_bayar = date('d/m/Y', $ts_bayar);
    $no_bayar  = date('Y', $ts_bayar) . '-' . date('dm', $ts_bayar) . '-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT);

    $periode = '';
    if (!empty($row['bulan_tagihan'])) {
        $ts_bln  = strtotime($row['bulan_tagihan'] . '-01');
        $periode = $bln_indo[(int)date('n', $ts_bln)] . ' ' . date('Y', $ts_bln);
    }

    $pot_text = $potongan > 0
        ? '- ' . rupiah($nominal_pot) . ' (' . $potongan . 'h)'
        : '—';

    $paket     = trim($row['nama_paket'] ?? '-');
    $sep_tebal = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
    $sep_tipis = "──────────────────────────────";
    $lw        = 30;

    $baris = function(string $label, string $nilai) use ($lw): string {
        $prefix = str_pad($label, 10) . ' : ';
        $sisa   = $lw - mb_strlen($prefix);
        $val    = mb_strlen($nilai) <= $sisa
                    ? str_pad($nilai, $sisa, ' ', STR_PAD_LEFT)
                    : $nilai;
        return $prefix . $val . "\n";
    };

    // Bangun blok struk monospace
    $metode_label = ($row['metode'] ?? 'tunai') === 'transfer' ? 'Transfer' : 'Tunai';

    $struk  = "```\n";
    $struk .= $sep_tebal . "\n";
    $struk .= $baris('No. Bayar',  $no_bayar);
    $struk .= $baris('Tgl. Bayar', $tgl_bayar);
    $struk .= $baris('Metode',     $metode_label);
    $struk .= $sep_tipis . "\n";
    $struk .= $baris('Pelanggan',  $row['nama_pelanggan']);
    $struk .= $baris('Paket',      $paket);
    if ($periode) $struk .= $baris('Periode', $periode);
    $struk .= $sep_tipis . "\n";
    $struk .= $baris('Tagihan',  rupiah($jumlah));
    $struk .= $baris('Potongan', $pot_text);
    $struk .= $sep_tebal . "\n";
    $struk .= str_pad('TOTAL BAYAR', 10) . ' : ' . str_pad(rupiah($terbayar), 16, ' ', STR_PAD_LEFT) . "\n";
    $struk .= $sep_tebal . "\n";

    // Pemakaian bulan lalu (bulan_tagihan - 1)
    $pemakaian_text = '';
    if (!empty($row['bulan_tagihan']) && !empty($row['pelanggan_id'])) {
        $bulan_lalu = date('Y-m', strtotime($row['bulan_tagihan'] . '-01 -1 month'));
        $usage = db_row(
            "SELECT bytes_out, uptime_seconds FROM usage_pppoe
             WHERE pelanggan_id = ? AND bulan_tagihan = ?",
            [(int)$row['pelanggan_id'], $bulan_lalu]
        );
        if ($usage && ((int)$usage['bytes_out'] > 0 || (int)$usage['uptime_seconds'] > 0)) {
            $bln_lalu_label = $bln_indo[(int)date('n', strtotime($bulan_lalu . '-01'))]
                            . ' ' . date('Y', strtotime($bulan_lalu . '-01'));
            $struk .= $sep_tipis . "\n";
            $struk .= "Pemakaian $bln_lalu_label\n";
            if ((int)$usage['bytes_out'] > 0)
                $struk .= $baris('Data', format_bytes((int)$usage['bytes_out']));
            if ((int)$usage['uptime_seconds'] > 0)
                $struk .= $baris('Online', format_uptime_seconds((int)$usage['uptime_seconds']));
            $pemakaian_text = "Data: " . format_bytes((int)$usage['bytes_out'])
                            . "\nOnline: " . format_uptime_seconds((int)$usage['uptime_seconds']);
        }
    }

    $struk .= "```";

    $result = wa_render('bukti_bayar', [
        'nama_isp'  => $nama_isp,
        'struk'     => $struk,
        'pemakaian' => $pemakaian_text,
    ]);

    // Fallback jika template belum ada di DB
    if (!$result) {
        $result  = "*BUKTI PEMBAYARAN IURAN*\n*{$nama_isp}*\n{$struk}\n";
        $result .= "Terima kasih sudah membayar! 🙏\n_SIMPATI · Powered by {$nama_isp}_";
    }

    return $result;
}

// ── Format Teks Pesan Pemberitahuan Isolir untuk WA ──────────
// $row harus mengandung: nama, no_hp, nama_paket, bulan_tagihan, jumlah
function format_pesan_isolir(array $row): string {
    $nama_isp = app_setting('nama_isp', 'KahfiNet');
    $no_cs    = app_setting('no_cs', '');
    $bln_indo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $periode  = '';
    if (!empty($row['bulan_tagihan'])) {
        $ts      = strtotime($row['bulan_tagihan'] . '-01');
        $periode = $bln_indo[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    }
    return wa_render('isolir', [
        'nama_isp' => $nama_isp,
        'nama'     => $row['nama'] ?? '-',
        'paket'    => $row['nama_paket'] ?? '-',
        'periode'  => $periode ?: date('Y-m'),
        'jumlah'   => rupiah((int)($row['jumlah'] ?? 0)),
        'no_cs'    => $no_cs,
    ]);
}

// ── Tambah WA ke antrian (wa_queue) ──────────────────────────
// Return true kalau berhasil masuk antrian, false kalau WA tidak aktif
function wa_queue_push(string $no_hp, string $pesan, string $tipe = 'bukti_bayar', int $pembayaran_id = 0): bool {
    if (app_setting('wablas_aktif', '0') !== '1') return false;
    $no = format_no_hp_wa($no_hp);
    if (strlen($no) < 10) return false;
    db_insert('wa_queue', [
        'pembayaran_id' => $pembayaran_id ?: null,
        'no_hp'         => $no,
        'pesan'         => $pesan,
        'tipe'          => $tipe,
        'status'        => 'pending',
        'attempts'      => 0,
        'created_at'    => date('Y-m-d H:i:s'),
    ]);
    return true;
}

// ── Kirim WA via Wablas ───────────────────────────────────────
// Return: ['ok' => bool, 'msg' => string]
function kirim_wa_wablas(string $no_hp, string $pesan, int $pembayaran_id = 0): array {
    if (app_setting('wablas_aktif', '0') !== '1') {
        return ['ok' => false, 'msg' => 'WA tidak aktif'];
    }
    $token  = app_setting('wablas_token', '');
    $secret = app_setting('wablas_secret', '');
    if (!$token || !$secret) {
        return ['ok' => false, 'msg' => 'Token/secret Wablas belum diisi'];
    }
    $no = format_no_hp_wa($no_hp);
    if (strlen($no) < 10) {
        return ['ok' => false, 'msg' => 'Nomor HP tidak valid'];
    }
    $ch = curl_init('https://deu.wablas.com/api/send-message');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['phone' => $no, 'message' => $pesan]),
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $token . '.' . $secret],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        $result = ['ok' => false, 'msg' => 'cURL: ' . $err];
    } else {
        $json   = json_decode($res, true);
        $ok     = isset($json['status']) && $json['status'] === true;
        $result = ['ok' => $ok, 'msg' => $json['message'] ?? 'No response'];
    }

    // Simpan log ke tabel wa_log
    if ($pembayaran_id > 0) {
        db_insert('wa_log', [
            'pembayaran_id' => $pembayaran_id,
            'no_hp'         => $no,
            'status'        => $result['ok'] ? 'terkirim' : 'gagal',
            'keterangan'    => mb_substr($result['msg'], 0, 255),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    return $result;
}

// ── Kirim WA via Fonnte ───────────────────────────────────────
function kirim_wa_fonnte(string $no_hp, string $pesan, int $pembayaran_id = 0): array {
    if (app_setting('wablas_aktif', '0') !== '1') {
        return ['ok' => false, 'msg' => 'WA tidak aktif'];
    }
    $token = app_setting('fonnte_token', '');
    if (!$token) {
        return ['ok' => false, 'msg' => 'Token Fonnte belum diisi'];
    }
    $no = format_no_hp_wa($no_hp);
    if (strlen($no) < 10) {
        return ['ok' => false, 'msg' => 'Nomor HP tidak valid'];
    }
    $ch = curl_init('https://api.fonnte.com/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'target'      => $no,
            'message'     => $pesan,
            'countryCode' => '62',
        ]),
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        $result = ['ok' => false, 'msg' => 'cURL: ' . $err];
    } else {
        $json   = json_decode($res, true);
        $ok     = isset($json['status']) && $json['status'] === true;
        $msg    = $ok ? ($json['detail'] ?? 'success') : ($json['reason'] ?? 'Gagal');
        $result = ['ok' => $ok, 'msg' => $msg];
    }

    if ($pembayaran_id > 0) {
        db_insert('wa_log', [
            'pembayaran_id' => $pembayaran_id,
            'no_hp'         => $no,
            'status'        => $result['ok'] ? 'terkirim' : 'gagal',
            'keterangan'    => mb_substr($result['msg'], 0, 255),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    return $result;
}

// ── Kirim WA (unified, routing ke gateway aktif) ──────────────
function kirim_wa(string $no_hp, string $pesan, int $pembayaran_id = 0): array {
    if (app_setting('wa_gateway', 'wablas') === 'fonnte') {
        return kirim_wa_fonnte($no_hp, $pesan, $pembayaran_id);
    }
    return kirim_wa_wablas($no_hp, $pesan, $pembayaran_id);
}

// ── POST / GET helper ─────────────────────────────────────────
function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}

function is_post(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// ── JSON Response (untuk AJAX) ────────────────────────────────
function json_res(bool $success, string $msg = '', array $data = []): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'msg' => $msg, 'data' => $data],
                     JSON_UNESCAPED_UNICODE);
    exit;
}
