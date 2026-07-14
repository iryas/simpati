<?php
// ============================================================
//  KAHFINET - Modul Mikrotik / PPP Secret (Tampilan)
//  Mirror read-only dari /ppp/secret (service pppoe) di Mikrotik.
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN, ROLE_TEKNISI]);

$lastSync = db_row("SELECT MAX(synced_at) as t FROM mikrotik_secrets_cache")['t'] ?? null;

$page_title  = 'Mikrotik — PPP Secret';
$active_menu = 'mikrotik_secret';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-key mr-2 text-primary"></i>PPP Secret (Mikrotik)</h5>
  <form method="POST" action="<?= BASE_URL ?>modules/mikrotik/act.php" class="d-inline">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="sync_secrets">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fas fa-sync mr-1"></i>Sync dari Mikrotik
    </button>
  </form>
</div>

<p class="text-muted" style="font-size:13px">
  Data ini cuma mirror dari <code>/ppp secret print</code> (service pppoe) di Mikrotik untuk dilihat — bukan untuk diedit di sini.
  <?= $lastSync ? 'Terakhir sync: ' . tgl_indo($lastSync, true) . '.' : 'Belum pernah sync.' ?>
</p>

<div class="card">
  <div class="card-body p-2">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap:8px">
      <div class="d-flex align-items-center" style="gap:6px">
        <label class="mb-0 text-muted" style="font-size:13px">Tampilkan</label>
        <select id="lengthSecret" class="form-control form-control-sm" style="width:70px">
          <option value="15">15</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <label class="mb-0 text-muted" style="font-size:13px">entri</label>
      </div>
      <input type="text" id="searchSecret" class="form-control form-control-sm"
             placeholder="Cari username, profile, komentar…" style="max-width:260px">
    </div>
    <div class="table-responsive">
      <table id="tabelSecret" class="table table-hover mb-0 w-100">
        <thead>
          <tr>
            <th>#</th>
            <th>Username</th>
            <th>Profile</th>
            <th>Remote Address</th>
            <th>Status</th>
            <th>Komentar</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<?php
$content  = ob_get_clean();
$base_url = BASE_URL;

$extra_js = <<<HTML
<script>
var tabelSecret = \$('#tabelSecret').DataTable({
  serverSide: true,
  processing: true,
  searching: true,
  dom: 'rt<"d-flex justify-content-between align-items-center mt-2 flex-wrap"ip>',
  pageLength: 15,
  order: [[1, 'asc']],
  language: { emptyTable:'Tidak ada data',info:'Menampilkan _START_-_END_ dari _TOTAL_ entri',infoEmpty:'0 entri',infoFiltered:'(dari _MAX_ total)',lengthMenu:'Tampilkan _MENU_ entri',loadingRecords:'Memuat...',processing:'Memproses...',zeroRecords:'Data tidak ditemukan',paginate:{first:'Pertama',last:'Terakhir',next:'›',previous:'‹'} },
  ajax: {
    url: '{$base_url}modules/mikrotik/act.php',
    data: function (d) {
      d.action = 'datatable_secrets';
    }
  },
  columns: [
    { data: 'no',             orderable: false },
    { data: 'name' },
    { data: 'profile' },
    { data: 'remote_address' },
    { data: 'status',         orderable: false },
    { data: 'comment',        orderable: false },
  ]
});

\$('#lengthSecret').on('change', function () {
  tabelSecret.page.len(parseInt(\$(this).val())).draw();
});

var searchTimer;
\$('#searchSecret').on('keyup', function () {
  clearTimeout(searchTimer);
  var q = \$(this).val();
  searchTimer = setTimeout(function () {
    tabelSecret.search(q).draw();
  }, 300);
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
