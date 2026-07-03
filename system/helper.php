<?php
// ============================================================
//  KAHFINET - Helper Functions (Diperkuat)
// ============================================================

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
        'kasir'   => 'success',
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
