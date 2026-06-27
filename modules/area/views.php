<?php
// ============================================================
//  KAHFINET - Modul Data Area (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();

$page_title  = 'Data Area';
$active_menu = 'area';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-map-marker-alt mr-2 text-primary"></i>Data Area</h5>
  <?php if (current_user()['role'] === ROLE_ADMIN): ?>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
      <i class="fas fa-plus mr-1"></i>Tambah Area
    </button>
  <?php endif; ?>
</div>

<!-- Tabel -->
<div class="card">
  <div class="card-body p-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:8px">
      <div class="d-flex align-items-center" style="gap:6px">
        <label class="mb-0 text-muted" style="font-size:13px">Tampilkan</label>
        <select id="lengthArea" class="form-control form-control-sm" style="width:70px">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <label class="mb-0 text-muted" style="font-size:13px">entri</label>
      </div>
      <div class="d-flex flex-nowrap" style="gap:8px">
        <input type="text" id="filterSearchArea" class="form-control form-control-sm"
          placeholder="Cari nama area…" style="min-width:180px;max-width:260px">
        <button type="button" id="btnCariArea" class="btn btn-primary btn-sm text-nowrap">
          <i class="fas fa-search mr-1"></i>Cari
        </button>
        <button type="button" id="btnResetArea" class="btn btn-secondary btn-sm text-nowrap">Reset</button>
      </div>
    </div>
    <div class="table-responsive">
      <table id="tabelArea" class="table table-hover mb-0 w-100">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Area</th>
            <th>Keterangan</th>
            <th>Jumlah Pelanggan</th>
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
        <h5 class="modal-title"><i class="fas fa-plus mr-2"></i>Tambah Area</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/area/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Nama Area <span class="text-danger">*</span></label>
            <input type="text" name="nama" class="form-control" placeholder="cth: Gang 1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan</label>
            <input type="text" name="keterangan" class="form-control" placeholder="opsional">
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
        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Area</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/area/act.php">
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

<style>.btn-xs{padding:3px 7px;font-size:12px;border-radius:4px}</style>

<?php
$content  = ob_get_clean();
$base_url = BASE_URL;

$extra_js = <<<HTML
<script>
var tabelArea = \$('#tabelArea').DataTable({
  serverSide: true,
  processing: true,
  searching: false,
  dom: 'rt<"d-flex justify-content-between align-items-center mt-2 flex-wrap"ip>',
  pageLength: 10,
  order: [[0, 'asc']],
  language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json' },
  ajax: {
    url: '{$base_url}modules/area/act.php',
    data: function (d) {
      d.action       = 'datatable';
      d.search.value = \$('#filterSearchArea').val();
    }
  },
  columns: [
    { data: 'no', orderable: false },
    { data: 'nama' },
    { data: 'keterangan', orderable: false },
    { data: 'jumlah_pelanggan', orderable: false },
    { data: 'aksi', orderable: false },
  ],
});

\$('#btnCariArea').on('click', function () { tabelArea.ajax.reload(); });
\$('#filterSearchArea').on('keyup', function (e) { if (e.key === 'Enter') tabelArea.ajax.reload(); });
\$('#btnResetArea').on('click', function () {
  \$('#filterSearchArea').val('');
  tabelArea.ajax.reload();
});
\$('#lengthArea').on('change', function () {
  tabelArea.page.len(parseInt(\$(this).val())).draw();
});

\$(document).on('click', '.btn-edit-area', function() {
  var id = \$(this).data('id');
  \$('#edit_id').val(id);
  \$('#editBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat&hellip;</div>');
  \$.getJSON('{$base_url}modules/area/act.php', { action: 'get_json', id: id }, function(res) {
    if (!res.success) { \$('#editBody').html('<p class="text-danger p-3">Gagal memuat data.</p>'); return; }
    var d = res.data;
    var esc = function(s) { return \$('<div>').text(s || '').html(); };
    \$('#editBody').html(
      '<div class="form-group">' +
        '<label class="form-label">Nama Area <span class="text-danger">*</span></label>' +
        '<input type="text" name="nama" class="form-control" value="' + esc(d.nama) + '" required>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Keterangan</label>' +
        '<input type="text" name="keterangan" class="form-control" value="' + esc(d.keterangan) + '">' +
      '</div>'
    );
  });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
