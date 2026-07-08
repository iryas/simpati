<?php
// ============================================================
//  KAHFINET - Modul Template WA (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/template_wa/views.php';

switch ($action) {

    case 'get_json':
        $id  = (int)get('id');
        $row = db_row("SELECT id, nama, konten, placeholder FROM wa_templates WHERE id = ?", [$id]);
        if (!$row) json_res(false, 'Template tidak ditemukan.');
        json_res(true, '', $row);

    case 'update':
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }

        $id     = (int)post('id');
        $nama   = mb_substr(trim(post('nama')), 0, 100);
        $konten = trim(post('konten'));

        if (!$id || !$nama || !$konten) {
            flash('danger', 'Semua field wajib diisi.');
            redirect($back_url);
        }

        db_update('wa_templates', [
            'nama'       => $nama,
            'konten'     => $konten,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        flash('success', 'Template "' . $nama . '" berhasil disimpan.');
        redirect($back_url);

    case 'toggle':
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }

        $id  = (int)post('id');
        $row = db_row("SELECT id, aktif, nama FROM wa_templates WHERE id = ?", [$id]);
        if (!$row) { flash('danger', 'Template tidak ditemukan.'); redirect($back_url); }

        $aktif_baru = $row['aktif'] ? 0 : 1;
        db_update('wa_templates', ['aktif' => $aktif_baru, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);

        $label = $aktif_baru ? 'diaktifkan' : 'dinonaktifkan';
        flash('success', 'Template "' . $row['nama'] . '" berhasil ' . $label . '.');
        redirect($back_url);

    default:
        redirect($back_url);
}
