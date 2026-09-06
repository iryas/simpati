<?php
// ============================================================
//  MONITORING · Modul Pemakaian — Cabut FUP Manual
//  Dipanggil dari tombol "Cabut FUP" di tab Kena FUP.
// ============================================================
require_once __DIR__ . '/../../_data.php';

$back_url = MONITORING_URL . 'modules/pemakaian/form.php?bulan=' . urlencode((string)($_GET['bulan'] ?? '')) . '&tab=fup';

if (!is_post() || !csrf_verify()) {
    flash('danger', 'Token tidak valid, coba lagi.');
    redirect($back_url);
}

$pelanggan_id = (int)post('pelanggan_id');
if ($pelanggan_id <= 0) {
    flash('danger', 'Pelanggan tidak valid.');
    redirect($back_url);
}

$hasil = fup_cabut_manual($pelanggan_id);
flash($hasil['ok'] ? 'success' : 'danger', $hasil['msg']);
redirect($back_url);
