<?php
// ============================================================
//  KAHFINET - Modul Pengeluaran (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KASIR]);

$action = get('action') ?: post('action');

$retBulan    = post('ret_bulan') ?: get('ret_bulan');
$retKategori = post('ret_kategori') ?: get('ret_kategori');

$backParams = [];
if ($retBulan !== '')    $backParams['bulan']    = $retBulan;
if ($retKategori !== '') $backParams['kategori'] = $retKategori;

$back_url = BASE_URL . 'modules/pengeluaran/views.php' . ($backParams ? '?' . http_build_query($backParams) : '');

$KATEGORI_VALID = ['bandwidth', 'listrik', 'lainnya'];

switch ($action) {

    case 'get_json':
        $row = db_row("SELECT * FROM pengeluaran WHERE id = ?", [(int)get('id')]);
        if (!$row) json_res(false, 'Tidak ditemukan.');
        json_res(true, '', $row);

        // ── CREATE ────────────────────────────────────────────────
    case 'create':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $kategori   = post('kategori');
        $keterangan = trim(post('keterangan'));
        $jumlah     = (int)post('jumlah');
        $tanggal    = post('tanggal') ?: date('Y-m-d');
        $bulan      = substr($tanggal, 0, 7);

        if (!in_array($kategori, $KATEGORI_VALID, true)) {
            flash('danger', 'Kategori tidak valid.');
            redirect($back_url);
        }
        if ($jumlah <= 0) {
            flash('danger', 'Nominal harus lebih dari 0.');
            redirect($back_url);
        }
        if ($kategori === 'lainnya' && $keterangan === '') {
            flash('danger', 'Keterangan wajib diisi untuk kategori Lainnya.');
            redirect($back_url);
        }

        if ($kategori !== 'lainnya') {
            $existing = db_row(
                "SELECT id FROM pengeluaran WHERE kategori = ? AND bulan = ?",
                [$kategori, $bulan]
            );
            if ($existing) {
                flash('danger', 'Pengeluaran kategori ' . ucfirst($kategori) . ' untuk bulan ini sudah ada. Edit data yang sudah ada saja.');
                redirect($back_url);
            }
        }

        db_insert('pengeluaran', [
            'kategori'     => $kategori,
            'keterangan'   => $keterangan !== '' ? $keterangan : null,
            'jumlah'       => $jumlah,
            'bulan'        => $bulan,
            'tanggal'      => $tanggal,
            'dicatat_oleh' => current_user()['id'],
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        flash('success', 'Pengeluaran berhasil dicatat.');
        redirect($back_url);

        // ── UPDATE ────────────────────────────────────────────────
    case 'update':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid.');
            redirect($back_url);
        }

        $id  = (int)post('id');
        $row = db_row("SELECT * FROM pengeluaran WHERE id = ?", [$id]);
        if (!$row) {
            flash('danger', 'Data tidak ditemukan.');
            redirect($back_url);
        }

        $kategori   = post('kategori');
        $keterangan = trim(post('keterangan'));
        $jumlah     = (int)post('jumlah');
        $tanggal    = post('tanggal') ?: $row['tanggal'];
        $bulan      = substr($tanggal, 0, 7);

        if (!in_array($kategori, $KATEGORI_VALID, true)) {
            flash('danger', 'Kategori tidak valid.');
            redirect($back_url);
        }
        if ($jumlah <= 0) {
            flash('danger', 'Nominal harus lebih dari 0.');
            redirect($back_url);
        }
        if ($kategori === 'lainnya' && $keterangan === '') {
            flash('danger', 'Keterangan wajib diisi untuk kategori Lainnya.');
            redirect($back_url);
        }

        if ($kategori !== 'lainnya') {
            $existing = db_row(
                "SELECT id FROM pengeluaran WHERE kategori = ? AND bulan = ? AND id != ?",
                [$kategori, $bulan, $id]
            );
            if ($existing) {
                flash('danger', 'Pengeluaran kategori ' . ucfirst($kategori) . ' untuk bulan ini sudah ada di baris lain.');
                redirect($back_url);
            }
        }

        db_update('pengeluaran', [
            'kategori'   => $kategori,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'jumlah'     => $jumlah,
            'bulan'      => $bulan,
            'tanggal'    => $tanggal,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        flash('success', 'Pengeluaran berhasil diperbarui.');
        redirect($back_url);

        // ── DELETE ────────────────────────────────────────────────
    case 'delete':
        auth_role([ROLE_ADMIN]);
        $id = (int)get('id');
        db_delete('pengeluaran', 'id = ?', [$id]);
        flash('success', 'Pengeluaran berhasil dihapus.');
        redirect($back_url);

    default:
        redirect($back_url);
}
