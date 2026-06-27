<?php
// ============================================================
//  KAHFINET - Modul Pengeluaran (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KASIR]);

$bulan    = get('bulan', date('Y-m'));
$kategori = get('kategori');

$where  = 'bulan = ?';
$params = [$bulan];
if ($kategori) {
    $where   .= ' AND kategori = ?';
    $params[] = $kategori;
}

$rows = db_rows(
    "SELECT pg.*, u.nama as nama_pencatat
     FROM pengeluaran pg
     LEFT JOIN pengguna u ON u.id = pg.dicatat_oleh
     WHERE $where
     ORDER BY FIELD(pg.kategori, 'bandwidth', 'listrik', 'lainnya') ASC, pg.tanggal ASC, pg.id ASC",
    $params
);

$totalPengeluaran = (int)db_row("SELECT COALESCE(SUM(jumlah),0) as n FROM pengeluaran WHERE bulan = ?", [$bulan])['n'];
$totalOmset       = (int)db_row("SELECT COALESCE(SUM(terbayar),0) as n FROM pembayaran WHERE status = 'lunas' AND bulan_tagihan = ?", [$bulan])['n'];
$keuntungan       = $totalOmset - $totalPengeluaran;

$badgeKategori = [
    'bandwidth' => ['Bandwidth', 'info'],
    'listrik'   => ['Listrik', 'warning'],
    'lainnya'   => ['Lainnya', 'secondary'],
];

$isAdmin = current_user()['role'] === ROLE_ADMIN;

$page_title  = 'Pengeluaran';
$active_menu = 'pengeluaran';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-receipt mr-2 text-primary"></i>Data Pengeluaran</h5>
  <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
    <i class="fas fa-plus mr-1"></i>Catat Pengeluaran
  </button>
</div>

<!-- Ringkasan -->
<div class="row mb-3">
  <div class="col-md-4 mb-2">
    <div class="card" style="border-left:4px solid #2563eb">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Omset Bulan <?= date('M Y', strtotime($bulan . '-01')) ?></div>
        <div class="font-weight-bold" style="font-size:18px; color:#2563eb"><?= rupiah($totalOmset) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card" style="border-left:4px solid #ef4444">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Pengeluaran Bulan <?= date('M Y', strtotime($bulan . '-01')) ?></div>
        <div class="font-weight-bold text-danger" style="font-size:18px"><?= rupiah($totalPengeluaran) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card" style="border-left:4px solid <?= $keuntungan >= 0 ? '#10b981' : '#ef4444' ?>">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Keuntungan Bersih</div>
        <div class="font-weight-bold <?= $keuntungan >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:18px"><?= rupiah($keuntungan) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="form-inline flex-wrap" style="gap:8px">
      <input type="month" name="bulan" class="form-control form-control-sm" value="<?= clean($bulan) ?>">
      <select name="kategori" class="form-control form-control-sm">
        <option value="">Semua Kategori</option>
        <option value="bandwidth" <?= $kategori === 'bandwidth' ? 'selected' : '' ?>>Bandwidth</option>
        <option value="listrik" <?= $kategori === 'listrik' ? 'selected' : '' ?>>Listrik</option>
        <option value="lainnya" <?= $kategori === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-search mr-1"></i>Filter
      </button>
      <a href="<?= BASE_URL ?>modules/pengeluaran/views.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
  </div>
</div>

<!-- Tabel -->
<div class="card">
  <div class="card-body p-2">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th style="width:40px">No</th>
            <th>Keterangan</th>
            <th class="text-right">Nominal</th>
            <th>Tgl. Pengeluaran</th>
            <th>Dicatat Oleh</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengeluaran untuk bulan ini.</td></tr>
          <?php endif; ?>
          <?php
          $groupNo  = 0;
          $lastKategori = null;
          foreach ($rows as $r):
              if ($r['kategori'] !== $lastKategori) {
                  $lastKategori = $r['kategori'];
                  $groupNo++;
                  [$label] = $badgeKategori[$r['kategori']];
          ?>
            <tr style="background:#fdf6e3">
              <td class="font-weight-bold"><?= $groupNo ?></td>
              <td class="font-weight-bold" colspan="5"><?= $label ?></td>
            </tr>
          <?php } ?>
            <tr>
              <td></td>
              <td>- <?= clean($r['keterangan'] ?? '—') ?></td>
              <td class="font-weight-bold text-right"><?= rupiah((int)$r['jumlah']) ?></td>
              <td class="text-muted"><?= tgl_indo($r['tanggal']) ?></td>
              <td class="text-muted"><?= clean($r['nama_pencatat'] ?? '—') ?></td>
              <td>
                <button class="btn btn-warning btn-xs btn-edit-pengeluaran" data-id="<?= (int)$r['id'] ?>" data-toggle="modal" data-target="#modalEdit"><i class="fas fa-edit"></i></button>
                <?php if ($isAdmin): ?>
                  <a href="<?= BASE_URL ?>modules/pengeluaran/act.php?action=delete&id=<?= (int)$r['id'] ?>&ret_bulan=<?= urlencode($bulan) ?>&ret_kategori=<?= urlencode($kategori) ?>"
                     class="btn btn-danger btn-xs btn-hapus" data-label="pengeluaran ini"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-receipt mr-2"></i>Catat Pengeluaran</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pengeluaran/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="ret_bulan" value="<?= clean($bulan) ?>">
        <input type="hidden" name="ret_kategori" value="<?= clean($kategori) ?>">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Kategori <span class="text-danger">*</span></label>
            <select name="kategori" class="form-control" id="tambahKategori" required>
              <option value="bandwidth">Bandwidth</option>
              <option value="listrik">Listrik</option>
              <option value="lainnya">Lainnya</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan <span class="text-danger" id="tambahKeteranganStar">*</span></label>
            <input type="text" name="keterangan" class="form-control" id="tambahKeterangan" placeholder="Cth: Tagihan upstream Juni, Beli kabel UTP 50m">
          </div>
          <div class="form-group">
            <label class="form-label">Nominal (Rp) <span class="text-danger">*</span></label>
            <input type="number" name="jumlah" class="form-control" min="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Pengeluaran</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pengeluaran/act.php" id="formEdit">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="ret_bulan" value="<?= clean($bulan) ?>">
        <input type="hidden" name="ret_kategori" value="<?= clean($kategori) ?>">
        <div class="modal-body" id="editBody">
          <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat data&hellip;</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning btn-sm">
            <i class="fas fa-save mr-1"></i>Update
          </button>
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
function toggleTambahKeterangan() {
  \$('#tambahKeteranganStar').toggle(\$('#tambahKategori').val() === 'lainnya');
}
\$('#tambahKategori').on('change', toggleTambahKeterangan);
\$('#modalTambah').on('show.bs.modal', toggleTambahKeterangan);

\$(document).on('click', '.btn-edit-pengeluaran', function () {
  var id = \$(this).data('id');
  \$('#edit_id').val(id);
  \$('#editBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat data&hellip;</div>');

  \$.getJSON('{$base_url}modules/pengeluaran/act.php', { action: 'get_json', id: id }, function (res) {
    if (!res.success) { \$('#editBody').html('<p class="text-danger p-3">Gagal memuat data.</p>'); return; }
    var d = res.data;
    var esc = function(s) { return \$('<div>').text(s || '').html(); };

    \$('#editBody').html(
      '<div class="form-group">' +
        '<label class="form-label">Kategori <span class="text-danger">*</span></label>' +
        '<select name="kategori" id="edit_kategori" class="form-control" required>' +
          '<option value="bandwidth"' + (d.kategori === 'bandwidth' ? ' selected' : '') + '>Bandwidth</option>' +
          '<option value="listrik"' + (d.kategori === 'listrik' ? ' selected' : '') + '>Listrik</option>' +
          '<option value="lainnya"' + (d.kategori === 'lainnya' ? ' selected' : '') + '>Lainnya</option>' +
        '</select>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Keterangan <span class="text-danger" id="edit_keterangan_star">*</span></label>' +
        '<input type="text" name="keterangan" class="form-control" value="' + esc(d.keterangan) + '">' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Nominal (Rp) <span class="text-danger">*</span></label>' +
        '<input type="number" name="jumlah" class="form-control" min="1" value="' + parseInt(d.jumlah) + '" required>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Tanggal <span class="text-danger">*</span></label>' +
        '<input type="date" name="tanggal" class="form-control" value="' + d.tanggal + '" required>' +
      '</div>'
    );

    function toggleEditKeterangan() {
      \$('#edit_keterangan_star').toggle(\$('#edit_kategori').val() === 'lainnya');
    }
    \$('#edit_kategori').on('change', toggleEditKeterangan);
    toggleEditKeterangan();
  });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
