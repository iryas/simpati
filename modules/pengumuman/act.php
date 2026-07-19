<?php
// ============================================================
//  KAHFINET - Modul Pengumuman (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/pengumuman/views.php';

$TIPE_VALID = ['info', 'penting', 'promo', 'maintenance'];

switch ($action) {

    // ── Ambil detail (untuk modal edit) ───────────────────────
    case 'get_json':
        $row = db_row("SELECT * FROM pengumuman WHERE id = ? LIMIT 1", [(int)get('id')]);
        if (!$row) json_res(false, 'Pengumuman tidak ditemukan.');
        json_res(true, '', $row);

    // ── Simpan (tambah / edit) ────────────────────────────────
    case 'simpan':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $id    = (int)post('id');
        $judul = trim(post('judul'));
        $isi   = trim(post('isi'));
        $tipe  = in_array(post('tipe'), $TIPE_VALID, true) ? post('tipe') : 'info';
        $pinned = isset($_POST['pinned']) ? 1 : 0;
        $aktif  = isset($_POST['aktif'])  ? 1 : 0;

        if ($judul === '' || $isi === '') {
            flash('danger', 'Judul dan isi pengumuman wajib diisi.');
            redirect($back_url);
        }
        if (mb_strlen($judul) > 150) $judul = mb_substr($judul, 0, 150);

        $data = [
            'judul'  => $judul,
            'isi'    => $isi,
            'tipe'   => $tipe,
            'pinned' => $pinned,
            'aktif'  => $aktif,
        ];

        if ($id) {
            db_update('pengumuman', $data, 'id = ?', [$id]);
            flash('success', 'Pengumuman berhasil diperbarui.');
        } else {
            $data['dibuat_oleh'] = current_user()['id'];
            $data['created_at']  = date('Y-m-d H:i:s');
            db_insert('pengumuman', $data);
            flash('success', 'Pengumuman berhasil ditambahkan.');
        }
        redirect($back_url);

    // ── Aktif / nonaktif cepat ────────────────────────────────
    case 'toggle':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }
        $id  = (int)post('id');
        $row = db_row("SELECT aktif FROM pengumuman WHERE id = ?", [$id]);
        if ($row) {
            db_update('pengumuman', ['aktif' => $row['aktif'] ? 0 : 1], 'id = ?', [$id]);
            flash('success', $row['aktif'] ? 'Pengumuman dinonaktifkan.' : 'Pengumuman diaktifkan.');
        }
        redirect($back_url);

    // ── Hapus ─────────────────────────────────────────────────
    case 'delete':
        $id = (int)get('id');
        if ($id) {
            db_delete('pengumuman', 'id = ?', [$id]);
            flash('success', 'Pengumuman dihapus.');
        }
        redirect($back_url);

    default:
        redirect($back_url);
}
