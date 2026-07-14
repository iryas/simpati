<?php
// ============================================================
//  KAHFINET - Modul Paket Internet (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN]);

$rows = db_rows("SELECT * FROM paket ORDER BY harga ASC");

$profiles = db_rows(
  "SELECT mpc.id, mpc.name, mpc.rate_limit, p.id as used_by_id, p.nama as used_by_nama
   FROM mikrotik_profiles_cache mpc
   LEFT JOIN paket p ON p.mikrotik_profiles_id = mpc.id
   ORDER BY mpc.name"
);

$page_title  = 'Paket Internet';
$active_menu = 'paket';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-box-open mr-2 text-primary"></i>Paket Internet</h5>
  <?php if (current_user()['role'] === ROLE_ADMIN): ?>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
      <i class="fas fa-plus mr-1"></i>Tambah Paket
    </button>
  <?php endif; ?>
</div>

<!-- Paket Cards -->
<div class="row mb-4">
  <?php if ($rows): ?>
    <?php foreach ($rows as $p): ?>
      <div class="col-md-4 col-sm-6 mb-3">
        <div class="card h-100" style="border-top:4px solid #2563eb">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="font-weight-bold mb-0" style="font-size:15px"><?= clean($p['nama']) ?></h6>
              <?= badge_status($p['status']) ?>
            </div>
            <div class="text-primary" style="font-size:22px;font-weight:700">
              <?= $p['kecepatan'] ?> Mbps
            </div>
            <div class="text-muted" style="font-size:12px">Kecepatan Download/Upload</div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
              <span class="font-weight-bold text-success" style="font-size:16px">
                <?= rupiah((int)$p['harga']) ?>
              </span>
              <span class="text-muted" style="font-size:12px">/ bulan</span>
            </div>
            <?php if ($p['keterangan']): ?>
              <p class="text-muted mt-2 mb-0" style="font-size:12px"><?= clean($p['keterangan']) ?></p>
            <?php endif; ?>
            <?php if (current_user()['role'] === ROLE_ADMIN): ?>
              <div class="mt-3 d-flex gap-2" style="gap:6px">
                <button class="btn btn-warning btn-xs btn-edit-paket flex-fill"
                  data-id="<?= $p['id'] ?>"
                  data-toggle="modal" data-target="#modalEdit">
                  <i class="fas fa-edit mr-1"></i>Edit
                </button>
                <a href="<?= BASE_URL ?>modules/paket/act.php?action=delete&id=<?= $p['id'] ?>"
                  class="btn btn-danger btn-xs btn-hapus"
                  data-label="paket <?= clean($p['nama']) ?>">
                  <i class="fas fa-trash mr-1"></i>Hapus
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="col-12">
      <div class="card">
        <div class="card-body text-center text-muted py-5">
          <i class="fas fa-box-open fa-3x mb-3"></i>
          <p>Belum ada paket internet.</p>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Table View -->
<div class="card">
  <div class="card-header">
    <span><i class="fas fa-list mr-2"></i>Daftar Semua Paket</span>
  </div>
  <div class="card-body p-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:8px">
      <div class="d-flex align-items-center" style="gap:6px">
        <label class="mb-0 text-muted" style="font-size:13px">Tampilkan</label>
        <select id="lengthPaket" class="form-control form-control-sm" style="width:70px">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <label class="mb-0 text-muted" style="font-size:13px">entri</label>
      </div>
      <div class="d-flex flex-nowrap" style="gap:8px">
        <input type="text" id="filterSearchPaket" class="form-control form-control-sm"
          placeholder="Cari nama paket / keterangan…" style="min-width:180px;max-width:280px">
        <button type="button" id="btnCariPaket" class="btn btn-primary btn-sm text-nowrap">
          <i class="fas fa-search mr-1"></i>Cari
        </button>
        <button type="button" id="btnResetPaket" class="btn btn-secondary btn-sm text-nowrap">Reset</button>
      </div>
    </div>
    <div class="table-responsive">
      <table id="tabelPaket" class="table table-hover mb-0 w-100">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Paket</th>
            <th>Kecepatan</th>
            <th>Harga / Bulan</th>
            <th>Keterangan</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus mr-2"></i>Tambah Paket</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/paket/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Profile PPP <span class="text-danger">*</span></label>
            <select name="mikrotik_profiles_id" class="form-control" required>
              <option value="">— Pilih Profile PPP —</option>
              <?php foreach ($profiles as $pf): ?>
                <option value="<?= $pf['id'] ?>" <?= $pf['used_by_id'] ? 'disabled' : '' ?>>
                  <?= clean($pf['name']) ?><?= $pf['rate_limit'] ? ' (' . clean($pf['rate_limit']) . ')' : '' ?>
                  <?= $pf['used_by_id'] ? ' — sudah dipakai: ' . clean($pf['used_by_nama']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (!$profiles): ?>
              <small class="text-danger">Belum ada data Profile PPP. Sync dulu dari menu Mikrotik &gt; Profile.</small>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Paket <span class="text-danger">*</span></label>
            <input type="text" name="nama" class="form-control" placeholder="cth: Paket 10 Mbps" required>
          </div>
          <div class="form-group">
            <label class="form-label">Kecepatan (Mbps) <span class="text-danger">*</span></label>
            <input type="number" name="kecepatan" class="form-control" placeholder="10" min="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Harga / Bulan (Rp) <span class="text-danger">*</span></label>
            <input type="number" name="harga" class="form-control" placeholder="150000" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Non-aktif</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan</label>
            <textarea name="keterangan" class="form-control" rows="2"></textarea>
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
        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Paket</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/paket/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body" id="editBody">
          <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i></div>
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

<style>
  .btn-xs {
    padding: 3px 7px;
    font-size: 12px;
    border-radius: 4px
  }
</style>

<?php
$content        = ob_get_clean();
$base_url       = BASE_URL;
$profiles_json  = json_encode(array_map(fn($pf) => [
  'id'         => $pf['id'],
  'name'       => $pf['name'],
  'rate_limit' => $pf['rate_limit'],
  'used_by_id' => $pf['used_by_id'],
  'used_by_nama' => $pf['used_by_nama'],
], $profiles));

$extra_js = <<<HTML
<script>
var profileOptions = {$profiles_json};

var tabelPaket = \$('#tabelPaket').DataTable({
  serverSide: true,
  processing: true,
  searching: false,
  dom: 'rt<"d-flex justify-content-between align-items-center mt-2 flex-wrap"ip>',
  pageLength: 10,
  order: [[3, 'asc']],
  language: { emptyTable:'Tidak ada data',info:'Menampilkan _START_-_END_ dari _TOTAL_ entri',infoEmpty:'0 entri',infoFiltered:'(dari _MAX_ total)',lengthMenu:'Tampilkan _MENU_ entri',loadingRecords:'Memuat...',processing:'Memproses...',zeroRecords:'Data tidak ditemukan',paginate:{first:'Pertama',last:'Terakhir',next:'›',previous:'‹'} },
  ajax: {
    url: '{$base_url}modules/paket/act.php',
    data: function (d) {
      d.action       = 'datatable';
      d.search.value = \$('#filterSearchPaket').val();
    }
  },
  columns: [
    { data: 'no', orderable: false },
    { data: 'nama' },
    { data: 'kecepatan' },
    { data: 'harga' },
    { data: 'keterangan', orderable: false },
    { data: 'status' },
    { data: 'aksi', orderable: false },
  ],
});

\$('#btnCariPaket').on('click', function () { tabelPaket.ajax.reload(); });
\$('#filterSearchPaket').on('keyup', function (e) { if (e.key === 'Enter') tabelPaket.ajax.reload(); });
\$('#btnResetPaket').on('click', function () {
  \$('#filterSearchPaket').val('');
  tabelPaket.ajax.reload();
});
\$('#lengthPaket').on('change', function () {
  tabelPaket.page.len(parseInt(\$(this).val())).draw();
});

$(document).on('click', '.btn-edit-paket', function() {
  var id = $(this).data('id');
  $('#edit_id').val(id);
  $('#editBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat&hellip;</div>');
  $.getJSON('{$base_url}modules/paket/act.php', { action: 'get_json', id: id }, function(res) {
    if (!res.success) { $('#editBody').html('<p class="text-danger p-3">Gagal memuat data.</p>'); return; }
    var d = res.data;

    var profileHtml = '<option value="">— Pilih Profile PPP —</option>';
    $.each(profileOptions, function(i, pf) {
      var isMine    = pf.id == d.mikrotik_profiles_id;
      var isUsed    = pf.used_by_id && !isMine;
      var label     = $('<div>').text(pf.name).html() + (pf.rate_limit ? ' (' + $('<div>').text(pf.rate_limit).html() + ')' : '');
      if (isUsed) label += ' — sudah dipakai: ' + $('<div>').text(pf.used_by_nama).html();
      profileHtml += '<option value="' + pf.id + '"' +
        (isMine ? ' selected' : '') +
        (isUsed ? ' disabled' : '') +
        '>' + label + '</option>';
    });

    $('#editBody').html(
      '<div class="form-group">' +
        '<label class="form-label">Profile PPP <span class="text-danger">*</span></label>' +
        '<select name="mikrotik_profiles_id" class="form-control" required>' + profileHtml + '</select>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Nama Paket <span class="text-danger">*</span></label>' +
        '<input type="text" name="nama" class="form-control" value="' + $('<div>').text(d.nama).html() + '" required>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Kecepatan (Mbps) <span class="text-danger">*</span></label>' +
        '<input type="number" name="kecepatan" class="form-control" value="' + parseInt(d.kecepatan) + '" min="1" required>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Harga / Bulan (Rp) <span class="text-danger">*</span></label>' +
        '<input type="number" name="harga" class="form-control" value="' + parseInt(d.harga) + '" min="0" required>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Status</label>' +
        '<select name="status" class="form-control">' +
          '<option value="aktif"'    + (d.status === 'aktif'    ? ' selected' : '') + '>Aktif</option>' +
          '<option value="nonaktif"' + (d.status === 'nonaktif' ? ' selected' : '') + '>Non-aktif</option>' +
        '</select>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Keterangan</label>' +
        '<textarea name="keterangan" class="form-control" rows="2">' + $('<div>').text(d.keterangan || '').html() + '</textarea>' +
      '</div>'
    );
  });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
