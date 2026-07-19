<?php
// ============================================================
//  KAHFINET - Modul Pengumuman (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$rows = db_rows(
    "SELECT pg.*, u.nama AS nama_pembuat
     FROM pengumuman pg
     LEFT JOIN pengguna u ON u.id = pg.dibuat_oleh
     ORDER BY pg.pinned DESC, pg.created_at DESC, pg.id DESC"
);

function pengumuman_tipe_meta(string $t): array {
    return [
        'info'        => ['primary', 'Info',         'fa-info-circle'],
        'penting'     => ['danger',  'Penting',      'fa-exclamation-circle'],
        'promo'       => ['success', 'Promo',        'fa-tags'],
        'maintenance' => ['warning', 'Pemeliharaan', 'fa-tools'],
    ][$t] ?? ['secondary', ucfirst($t), 'fa-bullhorn'];
}

$page_title  = 'Pengumuman';
$active_menu = 'pengumuman';

ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap">
  <h5 class="mb-0"><i class="fas fa-bullhorn mr-2 text-primary"></i>Info & Pengumuman</h5>
  <button class="btn btn-primary btn-sm btn-tambah" data-toggle="modal" data-target="#modalPengumuman">
    <i class="fas fa-plus mr-1"></i>Tambah Pengumuman
  </button>
</div>

<p class="text-muted" style="font-size:13px;margin-top:-6px">
  Pengumuman yang <strong>aktif</strong> akan tampil di Portal Pelanggan (beranda &amp; halaman Info).
</p>

<div class="card">
  <div class="card-body p-2">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th style="width:40px">No</th>
            <th>Judul</th>
            <th style="width:120px">Tipe</th>
            <th style="width:90px">Status</th>
            <th style="width:150px">Dibuat</th>
            <th style="width:150px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengumuman. Klik <strong>Tambah Pengumuman</strong> untuk membuat.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $i => $r): ?>
            <?php [$c, $lbl, $ic] = pengumuman_tipe_meta((string)$r['tipe']); ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <div class="font-weight-bold">
                  <?php if ((int)$r['pinned']): ?><i class="fas fa-thumbtack text-warning mr-1" title="Disematkan"></i><?php endif; ?>
                  <?= clean((string)$r['judul']) ?>
                </div>
                <div class="text-muted" style="font-size:12px"><?= clean(mb_strimwidth((string)$r['isi'], 0, 80, '…')) ?></div>
              </td>
              <td><span class="badge badge-<?= $c ?>"><i class="fas <?= $ic ?> mr-1"></i><?= $lbl ?></span></td>
              <td>
                <?php if ((int)$r['aktif']): ?>
                  <span class="badge badge-success">Aktif</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td class="text-muted" style="font-size:12px">
                <?= tgl_indo((string)$r['created_at']) ?><br>
                <span style="font-size:11px"><?= clean($r['nama_pembuat'] ?? '—') ?></span>
              </td>
              <td>
                <button class="btn btn-warning btn-xs btn-edit" data-id="<?= (int)$r['id'] ?>"
                        data-toggle="modal" data-target="#modalPengumuman" title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <form method="POST" action="<?= BASE_URL ?>modules/pengumuman/act.php" class="d-inline">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-<?= (int)$r['aktif'] ? 'secondary' : 'success' ?> btn-xs"
                          title="<?= (int)$r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                    <i class="fas fa-<?= (int)$r['aktif'] ? 'eye-slash' : 'eye' ?>"></i>
                  </button>
                </form>
                <a href="<?= BASE_URL ?>modules/pengumuman/act.php?action=delete&id=<?= (int)$r['id'] ?>"
                   class="btn btn-danger btn-xs" title="Hapus"
                   onclick="return confirm('Hapus pengumuman ini?')">
                  <i class="fas fa-trash"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalPengumuman" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-bullhorn mr-2"></i><span id="pg_modal_title">Tambah Pengumuman</span></h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pengumuman/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="simpan">
        <input type="hidden" name="id" id="pg_id" value="0">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Judul <span class="text-danger">*</span></label>
            <input type="text" name="judul" id="pg_judul" class="form-control" maxlength="150" required
                   placeholder="Cth: Pemeliharaan jaringan Sabtu malam">
          </div>
          <div class="form-group">
            <label class="form-label">Isi Pengumuman <span class="text-danger">*</span></label>
            <textarea name="isi" id="pg_isi" class="form-control" rows="5" required
                      placeholder="Tulis detail pengumuman di sini..."></textarea>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label class="form-label">Tipe</label>
              <select name="tipe" id="pg_tipe" class="form-control">
                <option value="info">Info</option>
                <option value="penting">Penting</option>
                <option value="promo">Promo</option>
                <option value="maintenance">Pemeliharaan</option>
              </select>
            </div>
            <div class="form-group col-md-6 d-flex align-items-end">
              <div>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="pg_aktif" name="aktif" checked>
                  <label class="custom-control-label" for="pg_aktif">Tampilkan di portal (aktif)</label>
                </div>
                <div class="custom-control custom-checkbox mt-1">
                  <input type="checkbox" class="custom-control-input" id="pg_pinned" name="pinned">
                  <label class="custom-control-label" for="pg_pinned">Sematkan di atas</label>
                </div>
              </div>
            </div>
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
// Tambah: reset form
\$(document).on('click', '.btn-tambah', function () {
  \$('#pg_modal_title').text('Tambah Pengumuman');
  \$('#pg_id').val('0');
  \$('#pg_judul').val('');
  \$('#pg_isi').val('');
  \$('#pg_tipe').val('info');
  \$('#pg_aktif').prop('checked', true);
  \$('#pg_pinned').prop('checked', false);
});

// Edit: muat data
\$(document).on('click', '.btn-edit', function () {
  var id = \$(this).data('id');
  \$('#pg_modal_title').text('Edit Pengumuman');
  \$('#pg_id').val(id);
  \$.getJSON('{$base_url}modules/pengumuman/act.php', { action: 'get_json', id: id }, function (res) {
    if (!res.success) { alert(res.msg || 'Gagal memuat'); return; }
    var d = res.data;
    \$('#pg_judul').val(d.judul);
    \$('#pg_isi').val(d.isi);
    \$('#pg_tipe').val(d.tipe);
    \$('#pg_aktif').prop('checked', d.aktif == 1);
    \$('#pg_pinned').prop('checked', d.pinned == 1);
  });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
