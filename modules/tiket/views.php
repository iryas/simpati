<?php
// ============================================================
//  KAHFINET - Modul Tiket Gangguan (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_TEKNISI]);

$filterStatus = get('status');
$STATUS_VALID = ['baru', 'diproses', 'selesai', 'ditutup'];

$where = '1=1';
$params = [];
if (in_array($filterStatus, $STATUS_VALID, true)) {
    $where   .= ' AND t.status = ?';
    $params[] = $filterStatus;
}

$rows = db_rows(
    "SELECT t.*, pl.nama AS nama_pelanggan, pl.no_hp, u.nama AS nama_petugas
     FROM tiket_gangguan t
     LEFT JOIN pelanggan pl ON pl.id = t.pelanggan_id
     LEFT JOIN pengguna  u  ON u.id  = t.ditangani_oleh
     WHERE $where
     ORDER BY FIELD(t.status,'baru','diproses','selesai','ditutup'), t.id DESC",
    $params
);

// Hitungan per status
$counts = ['baru' => 0, 'diproses' => 0, 'selesai' => 0, 'ditutup' => 0];
foreach (db_rows("SELECT status, COUNT(*) c FROM tiket_gangguan GROUP BY status") as $c) {
    $counts[$c['status']] = (int)$c['c'];
}

$KATEGORI_LABEL = [
    'koneksi_lambat' => 'Koneksi lambat',
    'tidak_konek'    => 'Tidak bisa konek',
    'perangkat'      => 'Perangkat/router',
    'tagihan'        => 'Tagihan',
    'lainnya'        => 'Lainnya',
];
function tiket_status_badge(string $s): string {
    $map = [
        'baru'     => ['info',      'Baru'],
        'diproses' => ['warning',   'Diproses'],
        'selesai'  => ['success',   'Selesai'],
        'ditutup'  => ['secondary', 'Ditutup'],
    ];
    [$c, $l] = $map[$s] ?? ['secondary', ucfirst($s)];
    return '<span class="badge badge-' . $c . '">' . $l . '</span>';
}

$page_title  = 'Tiket Gangguan';
$active_menu = 'tiket';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-headset mr-2 text-primary"></i>Laporan Gangguan Pelanggan</h5>
</div>

<!-- Ringkasan status -->
<div class="row mb-3">
  <?php
  $cards = [
      ['baru',     'Baru',     '#0ea5e9'],
      ['diproses', 'Diproses', '#f59e0b'],
      ['selesai',  'Selesai',  '#10b981'],
      ['ditutup',  'Ditutup',  '#64748b'],
  ];
  foreach ($cards as [$key, $lbl, $col]): ?>
    <div class="col-6 col-md-3 mb-2">
      <a href="<?= BASE_URL ?>modules/tiket/views.php?status=<?= $key ?>" class="text-decoration-none">
        <div class="card <?= $filterStatus === $key ? 'shadow' : '' ?>" style="border-left:4px solid <?= $col ?>">
          <div class="card-body py-3">
            <div class="text-muted" style="font-size:12px"><?= $lbl ?></div>
            <div class="font-weight-bold" style="font-size:20px;color:<?= $col ?>"><?= $counts[$key] ?></div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($filterStatus): ?>
  <div class="mb-2">
    <a href="<?= BASE_URL ?>modules/tiket/views.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-times mr-1"></i>Tampilkan semua
    </a>
  </div>
<?php endif; ?>

<!-- Tabel -->
<div class="card">
  <div class="card-body p-2">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th style="width:40px">No</th>
            <th>Pelanggan</th>
            <th>Kategori</th>
            <th>Keluhan</th>
            <th>Status</th>
            <th>Waktu</th>
            <th style="width:110px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada laporan gangguan<?= $filterStatus ? ' berstatus ini' : '' ?>.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <div class="font-weight-bold"><?= clean($r['nama_pelanggan'] ?? '—') ?></div>
                <div class="text-muted" style="font-size:12px"><?= clean($r['no_hp'] ?? '—') ?></div>
              </td>
              <td><?= clean($KATEGORI_LABEL[$r['kategori']] ?? $r['kategori']) ?></td>
              <td style="max-width:280px">
                <div style="white-space:normal;font-size:13px"><?= clean(mb_strimwidth((string)$r['deskripsi'], 0, 90, '…')) ?></div>
              </td>
              <td><?= tiket_status_badge((string)$r['status']) ?></td>
              <td class="text-muted" style="font-size:12px"><?= tgl_indo((string)$r['created_at'], true) ?></td>
              <td>
                <button class="btn btn-primary btn-xs btn-tanggapi" data-id="<?= (int)$r['id'] ?>"
                        data-toggle="modal" data-target="#modalTanggapi">
                  <i class="fas fa-reply mr-1"></i>Tanggapi
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tanggapi -->
<div class="modal fade" id="modalTanggapi" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-headset mr-2"></i>Tanggapi Laporan</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/tiket/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="t_id">
        <input type="hidden" name="ret_status" value="<?= clean((string)$filterStatus) ?>">
        <div class="modal-body">
          <div class="card mb-3" style="background:#f8fafc;border:0">
            <div class="card-body py-2">
              <div class="row" style="font-size:13px">
                <div class="col-md-6"><strong>Pelanggan:</strong> <span id="t_nama">—</span></div>
                <div class="col-md-6"><strong>No HP:</strong> <span id="t_hp">—</span></div>
                <div class="col-md-6 mt-1"><strong>Kategori:</strong> <span id="t_kategori">—</span></div>
                <div class="col-md-6 mt-1"><strong>Waktu:</strong> <span id="t_waktu">—</span></div>
              </div>
              <hr class="my-2">
              <div style="font-size:13px"><strong>Keluhan:</strong></div>
              <div id="t_deskripsi" style="font-size:13px;white-space:pre-wrap;color:#334155">—</div>
              <div class="text-muted mt-1" style="font-size:11px" id="t_petugas_wrap">
                Terakhir ditangani: <span id="t_petugas">—</span>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Status <span class="text-danger">*</span></label>
            <select name="status" id="t_status" class="form-control" required>
              <option value="baru">Baru</option>
              <option value="diproses">Sedang Diproses</option>
              <option value="selesai">Selesai</option>
              <option value="ditutup">Ditutup</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggapan untuk pelanggan</label>
            <textarea name="balasan" id="t_balasan" class="form-control" rows="4"
                      maxlength="1000" placeholder="Cth: Sudah kami cek, ada gangguan di ODP area Anda. Estimasi normal 2 jam lagi."></textarea>
          </div>
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="t_kirim_wa" name="kirim_wa">
            <label class="custom-control-label" for="t_kirim_wa">
              Kirim tanggapan ini ke pelanggan via WhatsApp
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>.btn-xs{padding:3px 7px;font-size:12px;border-radius:4px}</style>

<?php
$content  = ob_get_clean();
$base_url = BASE_URL;

$extra_js = <<<HTML
<script>
\$(document).on('click', '.btn-tanggapi', function () {
  var id = \$(this).data('id');
  \$('#t_id').val(id);
  \$('#t_nama,#t_hp,#t_kategori,#t_waktu,#t_deskripsi,#t_petugas').text('memuat…');
  \$('#t_balasan').val('');
  \$('#t_kirim_wa').prop('checked', false);

  \$.getJSON('{$base_url}modules/tiket/act.php', { action: 'get_json', id: id }, function (res) {
    if (!res.success) { alert(res.msg || 'Gagal memuat'); return; }
    var d = res.data;
    \$('#t_nama').text(d.nama_pelanggan || '—');
    \$('#t_hp').text(d.no_hp || '—');
    \$('#t_kategori').text(d.kategori_label || d.kategori);
    \$('#t_waktu').text(d.created_at || '—');
    \$('#t_deskripsi').text(d.deskripsi || '—');
    \$('#t_petugas').text(d.nama_petugas || 'belum ada');
    \$('#t_status').val(d.status);
    \$('#t_balasan').val(d.balasan || '');
  });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
