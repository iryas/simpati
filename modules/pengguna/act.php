<?php
// ============================================================
//  KAHFINET - Modul Pengguna (Action Handler - Diperkuat)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/pengguna/views.php';

switch ($action) {

    case 'get_json':
        $row = db_row("SELECT id,nama,username,role,status FROM pengguna WHERE id = ?", [(int)get('id')]);
        if (!$row) json_res(false, 'Tidak ditemukan.');
        json_res(true, '', $row);

    case 'create':
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }

        $rules = [
            'nama'     => ['label' => 'Nama',     'required' => true, 'max' => 100],
            'username' => ['label' => 'Username', 'required' => true, 'max' => 50,
                           'regex' => '/^[a-zA-Z0-9_]+$/'],
            'password' => ['label' => 'Password', 'required' => true, 'min' => 8, 'max' => 255],
        ];
        $errs = validate_input($rules, $_POST);
        if ($errs) { flash('danger', implode(' ', $errs)); redirect($back_url); }

        $username = trim(post('username'));
        $password = post('password');
        $role     = post('role');

        if (!in_array($role, [ROLE_ADMIN, ROLE_TEKNISI, ROLE_KASIR], true)) {
            flash('danger', 'Role tidak valid.'); redirect($back_url);
        }

        $exist = db_row("SELECT id FROM pengguna WHERE username = ?", [$username]);
        if ($exist) { flash('danger', 'Username sudah digunakan.'); redirect($back_url); }

        db_insert('pengguna', [
            'nama'       => trim(post('nama')),
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role'       => $role,
            'status'     => post('status') === 'aktif' ? 'aktif' : 'nonaktif',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        flash('success', 'Pengguna berhasil ditambahkan.');
        redirect($back_url);

    case 'update':
        if (!csrf_verify()) { flash('danger', 'Token tidak valid.'); redirect($back_url); }

        $id       = (int)post('id');
        $username = trim(post('username'));
        $role     = post('role');

        if (!$id) { flash('danger', 'ID tidak valid.'); redirect($back_url); }
        if (!in_array($role, [ROLE_ADMIN, ROLE_TEKNISI, ROLE_KASIR], true)) {
            flash('danger', 'Role tidak valid.'); redirect($back_url);
        }

        $rules = [
            'nama'     => ['label' => 'Nama',     'required' => true, 'max' => 100],
            'username' => ['label' => 'Username', 'required' => true, 'max' => 50,
                           'regex' => '/^[a-zA-Z0-9_]+$/'],
        ];
        $errs = validate_input($rules, $_POST);
        if ($errs) { flash('danger', implode(' ', $errs)); redirect($back_url); }

        // Cek duplikat username (selain diri sendiri)
        $exist = db_row("SELECT id FROM pengguna WHERE username = ? AND id != ?", [$username, $id]);
        if ($exist) { flash('danger', 'Username sudah digunakan.'); redirect($back_url); }

        // Cegah admin hapus role diri sendiri
        if ($id === current_user()['id'] && $role !== ROLE_ADMIN) {
            flash('danger', 'Anda tidak bisa mengubah role akun sendiri.'); redirect($back_url);
        }

        $data = [
            'nama'       => trim(post('nama')),
            'username'   => $username,
            'role'       => $role,
            'status'     => post('status') === 'aktif' ? 'aktif' : 'nonaktif',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $password = post('password');
        if (!empty($password)) {
            if (mb_strlen($password) < 8) {
                flash('danger', 'Password minimal 8 karakter.'); redirect($back_url);
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            // Hapus semua remember token user ini karena password berubah
            db_query("DELETE FROM remember_tokens WHERE pengguna_id = ?", [$id]);
        }

        db_update('pengguna', $data, 'id = ?', [$id]);
        flash('success', 'Data pengguna berhasil diperbarui.');
        redirect($back_url);

    case 'delete':
        $id = (int)get('id');
        if (!$id) { flash('danger', 'ID tidak valid.'); redirect($back_url); }

        if ($id === current_user()['id']) {
            flash('danger', 'Anda tidak bisa menghapus akun sendiri.');
            redirect($back_url);
        }
        // Hapus remember token juga
        db_query("DELETE FROM remember_tokens WHERE pengguna_id = ?", [$id]);
        db_delete('pengguna', 'id = ?', [$id]);
        flash('success', 'Pengguna berhasil dihapus.');
        redirect($back_url);

    default:
        redirect($back_url);
}
