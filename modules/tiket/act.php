<?php
// ============================================================
//  KAHFINET - Modul Tiket Gangguan (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_TEKNISI]);

$action = get('action') ?: post('action');

$retStatus = post('ret_status') ?: get('ret_status');
$backParams = [];
if ($retStatus !== '') $backParams['status'] = $retStatus;
$back_url = BASE_URL . 'modules/tiket/views.php' . ($backParams ? '?' . http_build_query($backParams) : '');

$STATUS_VALID = ['baru', 'diproses', 'selesai', 'ditutup'];

$KATEGORI_LABEL = [
    'koneksi_lambat' => 'Koneksi lambat',
    'tidak_konek'    => 'Tidak bisa konek',
    'perangkat'      => 'Perangkat/router',
    'tagihan'        => 'Tagihan',
    'lainnya'        => 'Lainnya',
];
$STATUS_LABEL = [
    'baru' => 'Baru', 'diproses' => 'Sedang Diproses',
    'selesai' => 'Selesai', 'ditutup' => 'Ditutup',
];

switch ($action) {

    // ── Ambil detail tiket (untuk modal) ──────────────────────
    case 'get_json':
        $row = db_row(
            "SELECT t.*, pl.nama AS nama_pelanggan, pl.no_hp, pl.status AS status_pelanggan,
                    u.nama AS nama_petugas
             FROM tiket_gangguan t
             LEFT JOIN pelanggan pl ON pl.id = t.pelanggan_id
             LEFT JOIN pengguna  u  ON u.id  = t.ditangani_oleh
             WHERE t.id = ? LIMIT 1",
            [(int)get('id')]
        );
        if (!$row) json_res(false, 'Tiket tidak ditemukan.');
        $row['kategori_label'] = $KATEGORI_LABEL[$row['kategori']] ?? $row['kategori'];
        json_res(true, '', $row);

    // ── Perbarui status / balasan ─────────────────────────────
    case 'update':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $id      = (int)post('id');
        $status  = post('status');
        $balasan = trim(post('balasan'));
        $kirimWa = isset($_POST['kirim_wa']);

        if (!in_array($status, $STATUS_VALID, true)) {
            flash('danger', 'Status tidak valid.');
            redirect($back_url);
        }
        if (mb_strlen($balasan) > 1000) $balasan = mb_substr($balasan, 0, 1000);

        $tiket = db_row(
            "SELECT t.*, pl.nama AS nama_pelanggan, pl.no_hp
             FROM tiket_gangguan t
             LEFT JOIN pelanggan pl ON pl.id = t.pelanggan_id
             WHERE t.id = ? LIMIT 1",
            [$id]
        );
        if (!$tiket) {
            flash('danger', 'Tiket tidak ditemukan.');
            redirect($back_url);
        }

        db_update('tiket_gangguan', [
            'status'         => $status,
            'balasan'        => $balasan !== '' ? $balasan : null,
            'ditangani_oleh' => current_user()['id'],
        ], 'id = ?', [$id]);

        // Notifikasi WA opsional
        $waMsg = '';
        if ($kirimWa && !empty($tiket['no_hp'])) {
            $nama_isp = app_setting('nama_isp', 'KahfiNet');
            $kat      = $KATEGORI_LABEL[$tiket['kategori']] ?? $tiket['kategori'];
            $pesan  = "*Update Laporan Gangguan* — {$nama_isp}\n\n";
            $pesan .= "Halo " . $tiket['nama_pelanggan'] . ", laporan Anda (" . $kat . ") ";
            $pesan .= "kini berstatus: *" . ($STATUS_LABEL[$status] ?? $status) . "*.\n";
            if ($balasan !== '') $pesan .= "\n" . $balasan . "\n";
            $pesan .= "\nTerima kasih atas kesabarannya 🙏";

            try {
                $res = kirim_wa($tiket['no_hp'], $pesan);
                $waMsg = ($res['success'] ?? false) ? ' Notifikasi WA terkirim.' : ' (Catatan: WA gagal terkirim.)';
            } catch (Throwable $e) {
                $waMsg = ' (Catatan: WA gagal terkirim.)';
            }
        }

        flash('success', 'Tiket diperbarui.' . $waMsg);
        redirect($back_url);

    default:
        redirect($back_url);
}
