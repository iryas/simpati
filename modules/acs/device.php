<?php
// ============================================================
//  KAHFINET - Modul ACS / Device ONU (Tampilan)
//  Mirror read-only dari /devices GenieACS. Mapping ke Secret PPP
//  dilakukan MANUAL (bukan auto-match), supaya admin tetap pegang
//  kendali kalau username PPPoE di device tidak konsisten.
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN, ROLE_TEKNISI]);

$secrets = db_rows(
    "SELECT msc.id, msc.name, msc.genieacs_device_id, pl.nama as nama_pelanggan
     FROM mikrotik_secrets_cache msc
     LEFT JOIN pelanggan pl ON pl.mikrotik_secrets_id = msc.id
     ORDER BY msc.name ASC"
);

$lastSync = db_row("SELECT MAX(synced_at) as t FROM genieacs_devices_cache")['t'] ?? null;

$page_title  = 'ACS — Device ONU';
$active_menu = 'acs_device';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-router mr-2 text-primary"></i>Device ONU</h5>
  <form method="POST" action="<?= BASE_URL ?>modules/acs/act.php" class="d-inline">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="sync_devices">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fas fa-sync mr-1"></i>Sync dari ACS
    </button>
  </form>
</div>

<p class="text-muted" style="font-size:13px">
  Data ini mirror dari GenieACS — bukan untuk diedit di sini. Mapping ke Secret PPP/pelanggan dipilih manual lewat tombol di kolom Mapping.
  <?= $lastSync ? 'Terakhir sync: ' . tgl_indo($lastSync, true) . '.' : 'Belum pernah sync.' ?>
</p>

<div class="card">
  <div class="card-body p-2">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap:8px">
      <div class="d-flex align-items-center" style="gap:6px">
        <label class="mb-0 text-muted" style="font-size:13px">Tampilkan</label>
        <select id="lengthDevice" class="form-control form-control-sm" style="width:70px">
          <option value="15">15</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <label class="mb-0 text-muted" style="font-size:13px">entri</label>
      </div>
      <input type="text" id="searchDevice" class="form-control form-control-sm"
             placeholder="Cari tag, model, pelanggan…" style="max-width:260px">
    </div>
    <div class="table-responsive">
      <table id="tabelDevice" class="table table-hover mb-0 w-100">
        <thead>
          <tr>
            <th>#</th>
            <th>Tag</th>
            <th>Model</th>
            <th>Last Inform</th>
            <th>Mapping</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Mapping -->
<div class="modal fade" id="modalMapping" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-link mr-2"></i>Mapping Device ONU</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/acs/act.php" id="formMapping">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="set_mapping">
        <input type="hidden" name="device_id" id="mapping_device_id">
        <div class="modal-body">
          <table class="w-100 mb-3" style="font-size:13px">
            <tr><td class="text-muted">Tag device</td><td class="text-right font-weight-bold" id="mapping_tag"></td></tr>
            <tr><td class="text-muted">Model</td><td class="text-right" id="mapping_model"></td></tr>
          </table>
          <div class="form-group">
            <label class="form-label">Pilih Secret PPP / Pelanggan</label>
            <select name="secret_id" id="mapping_secret_id" class="form-control" style="width:100%">
              <option value="">— Tidak dimapping —</option>
            </select>
            <small class="text-muted">Opsi yang ditandai "username cocok" berarti username PPPoE sama dengan yang dilaporkan device — bukan otomatis dipilih, tetap perlu dikonfirmasi manual.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Mapping
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>.btn-xs{padding:3px 7px;font-size:12px;border-radius:4px}</style>

<?php
$content      = ob_get_clean();
$base_url     = BASE_URL;
$secrets_json = json_encode(array_map(fn($s) => [
    'id'                 => $s['id'],
    'name'               => $s['name'],
    'nama_pelanggan'     => $s['nama_pelanggan'],
    'genieacs_device_id' => $s['genieacs_device_id'],
], $secrets));

$extra_js = <<<HTML
<script>
var tabelDevice = \$('#tabelDevice').DataTable({
  serverSide: true,
  processing: true,
  searching: true,
  dom: 'rt<"d-flex justify-content-between align-items-center mt-2 flex-wrap"ip>',
  pageLength: 15,
  order: [[1, 'asc']],
  language: { emptyTable:'Tidak ada data',info:'Menampilkan _START_-_END_ dari _TOTAL_ entri',infoEmpty:'0 entri',infoFiltered:'(dari _MAX_ total)',lengthMenu:'Tampilkan _MENU_ entri',loadingRecords:'Memuat...',processing:'Memproses...',zeroRecords:'Data tidak ditemukan',paginate:{first:'Pertama',last:'Terakhir',next:'›',previous:'‹'} },
  ajax: {
    url: '{$base_url}modules/acs/act.php',
    data: function (d) {
      d.action = 'datatable_devices';
    }
  },
  columns: [
    { data: 'no',          orderable: false },
    { data: 'tag' },
    { data: 'model',       orderable: false },
    { data: 'last_inform', orderable: false },
    { data: 'mapping',     orderable: false },
  ]
});

\$('#lengthDevice').on('change', function () {
  tabelDevice.page.len(parseInt(\$(this).val())).draw();
});

var searchTimer;
\$('#searchDevice').on('keyup', function () {
  clearTimeout(searchTimer);
  var q = \$(this).val();
  searchTimer = setTimeout(function () {
    tabelDevice.search(q).draw();
  }, 300);
});

// ── Modal Mapping ──────────────────────────────────────────────
\$('#mapping_secret_id').select2({
  theme: 'bootstrap',
  dropdownParent: \$('#modalMapping'),
  placeholder: '— Tidak dimapping —',
  width: '100%',
});

var secretOptions = {$secrets_json};

\$(document).on('click', '.btn-mapping', function () {
  var deviceId        = \$(this).data('device-id');
  var tag             = \$(this).data('tag');
  var model           = \$(this).data('model');
  var pppoeUsername   = \$(this).data('pppoe');
  var currentSecretId = \$(this).data('secret-id');

  \$('#mapping_device_id').val(deviceId);
  \$('#mapping_tag').text(tag);
  \$('#mapping_model').text(model || '—');

  var \$select = \$('#mapping_secret_id');
  \$select.find('option:not(:first)').remove();

  \$.each(secretOptions, function (i, s) {
    var isMine  = s.id == currentSecretId;
    var isUsed  = s.genieacs_device_id && s.genieacs_device_id != deviceId && !isMine;
    var isMatch = pppoeUsername && s.name === pppoeUsername;
    var label   = s.name + (s.nama_pelanggan ? ' — ' + s.nama_pelanggan : '');
    if (isMatch) label += ' ✓ username cocok';
    if (isUsed)  label += ' (sudah dipakai device lain)';
    var \$opt = \$('<option></option>').val(s.id).text(label);
    if (isUsed) \$opt.prop('disabled', true);
    \$select.append(\$opt);
  });

  \$select.val(currentSecretId || '').trigger('change');
  \$('#modalMapping').modal('show');
});

\$('#modalMapping').on('hidden.bs.modal', function () {
  \$('#mapping_secret_id').val('').trigger('change');
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
