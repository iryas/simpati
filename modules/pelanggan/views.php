<?php
// ============================================================
//  KAHFINET - Modul Pelanggan (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();

$pakets = db_rows(
  "SELECT pk.id, pk.nama, mpc.rate_limit
   FROM paket pk
   LEFT JOIN mikrotik_profiles_cache mpc ON mpc.id = pk.mikrotik_profiles_id
   WHERE pk.status='aktif' AND pk.nama != 'Isolir Pelanggan'
   ORDER BY pk.kecepatan ASC"
);
$areas  = db_rows("SELECT id, nama, keterangan FROM area ORDER BY id");

// Kelompokkan area berdasarkan keterangan (mis. nama desa) untuk <optgroup>.
$areaGroups = [];
foreach ($areas as $a) {
  $groupLabel = trim($a['keterangan']) !== '' ? $a['keterangan'] : 'Lainnya';
  $areaGroups[$groupLabel][] = $a;
}

function render_area_optgroups(array $areaGroups): void {
  foreach ($areaGroups as $label => $items) {
    echo '<optgroup label="' . clean($label) . '">';
    foreach ($items as $a) {
      echo '<option value="' . $a['id'] . '">' . clean($a['nama']) . '</option>';
    }
    echo '</optgroup>';
  }
}

$secrets = db_rows(
  "SELECT msc.id, msc.name, msc.profile, msc.disabled, pl.id as used_by_id, pl.nama as used_by_nama
   FROM mikrotik_secrets_cache msc
   LEFT JOIN pelanggan pl ON pl.mikrotik_secrets_id = msc.id
   ORDER BY msc.name"
);

// ── Render ──────────────────────────────────────────────────
$page_title  = 'Data Pelanggan';
$active_menu = 'pelanggan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-users mr-2 text-primary"></i>Data Pelanggan</h5>
  <?php if (in_array(current_user()['role'], [ROLE_ADMIN, ROLE_TEKNISI])): ?>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
      <i class="fas fa-plus mr-1"></i>Tambah Pelanggan
    </button>
  <?php endif; ?>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
      <div class="d-flex align-items-center" style="gap:6px">
        <label class="mb-0 text-muted" style="font-size:13px">Tampilkan</label>
        <select id="lengthPelanggan" class="form-control form-control-sm" style="width:70px">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <label class="mb-0 text-muted" style="font-size:13px">entri</label>
      </div>
      <div class="d-flex flex-nowrap" style="gap:8px">
        <input type="text" id="filterSearch" class="form-control form-control-sm"
          placeholder="Cari nama / no HP / alamat…" style="min-width:180px;max-width:260px">
        <select id="filterStatus" class="form-control form-control-sm">
          <option value="">Semua Status</option>
          <option value="aktif">Aktif</option>
          <option value="nonaktif">Non-aktif</option>
          <option value="isolir">Isolir</option>
        </select>
        <select id="filterArea" class="form-control form-control-sm">
          <option value="">Semua Area</option>
          <?php render_area_optgroups($areaGroups); ?>
        </select>
        <button type="button" id="btnCari" class="btn btn-primary btn-sm text-nowrap">
          <i class="fas fa-search mr-1"></i>Cari
        </button>
        <button type="button" id="btnReset" class="btn btn-secondary btn-sm text-nowrap">Reset</button>
      </div>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-body p-3">
    <div class="table-responsive">
      <table id="tabelPelanggan" class="table table-hover mb-0 w-100">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama</th>
            <th>No. HP</th>
            <th>Alamat</th>
            <th>Area</th>
            <th>Paket</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Modal Tambah ────────────────────────────────────────── -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Tambah Pelanggan</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pelanggan/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label class="form-label">Secret PPP <span class="text-danger">*</span></label>
                <select name="mikrotik_secrets_id" id="selectSecretTambah" class="form-control" required>
                  <option value="">— Pilih Secret PPP —</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" name="nama" class="form-control" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">No. HP / WhatsApp</label>
                <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx">
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label class="form-label">Alamat</label>
                <textarea name="alamat" class="form-control" rows="2"></textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Area</label>
                <select name="area_id" class="form-control">
                  <option value="">— Pilih Area —</option>
                  <?php render_area_optgroups($areaGroups); ?>
                </select>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label class="form-label">Paket Internet</label>
                <div class="paket-radio-group">
                  <?php foreach ($pakets as $pk): ?>
                    <label class="paket-radio-item">
                      <input type="radio" name="paket_id" value="<?= $pk['id'] ?>">
                      <span><?= clean($pk['nama']) ?><?= $pk['rate_limit'] ? ' (' . clean($pk['rate_limit']) . ')' : '' ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Tanggal Daftar</label>
                <input type="date" name="tgl_daftar" class="form-control"
                  value="<?= date('Y-m-d') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <option value="aktif">Aktif</option>
                  <option value="nonaktif">Non-aktif</option>
                  <option value="isolir">Isolir</option>
                </select>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label class="form-label">Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="2"></textarea>
              </div>
            </div>
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

<!-- ── Modal Detail ───────────────────────────────────────────── -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user mr-2"></i>Detail Pelanggan</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" id="detailBody">
        <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat data&hellip;</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Edit ─────────────────────────────────────────────── -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Pelanggan</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pelanggan/act.php" id="formEdit">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body" id="editBody">
          <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat data…</div>
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
    border-radius: 4px;
  }
  .paket-radio-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .paket-radio-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13.5px;
    margin: 0;
    transition: border-color .15s, background-color .15s;
  }
  .paket-radio-item input[type="radio"] {
    margin: 0;
  }
  .paket-radio-item:hover {
    border-color: #2563eb;
  }
  .paket-radio-item input[type="radio"]:checked ~ span {
    font-weight: 600;
    color: #2563eb;
  }
  .paket-radio-item:has(input[type="radio"]:checked) {
    border-color: #2563eb;
    background-color: #eff6ff;
  }
</style>

<?php
$content      = ob_get_clean();
$base_url     = BASE_URL;
$paket_json   = json_encode(array_map(fn($p) => ['id' => $p['id'], 'nama' => $p['nama'], 'rate_limit' => $p['rate_limit']], $pakets));
$area_json    = json_encode(array_map(fn($a) => [
  'id'         => $a['id'],
  'nama'       => $a['nama'],
  'keterangan' => $a['keterangan'],
], $areas));
$secrets_json = json_encode(array_map(fn($sc) => [
  'id'           => $sc['id'],
  'name'         => $sc['name'],
  'profile'      => $sc['profile'],
  'used_by_id'   => $sc['used_by_id'],
  'used_by_nama' => $sc['used_by_nama'],
], $secrets));

$extra_js = <<<HTML
<script>
var paketOptions   = {$paket_json};
var secretOptions  = {$secrets_json};
var areaOptions    = {$area_json};

// Select2 AJAX — Secret PPP modal Tambah
\$('#selectSecretTambah').select2({
  dropdownParent: \$('#modalTambah'),
  placeholder: '— Pilih Secret PPP —',
  width: '100%',
  minimumInputLength: 0,
  ajax: {
    url: '{$base_url}api/search_mikrotik_secret.php',
    dataType: 'json',
    delay: 250,
    data: function (p) { return { q: p.term || '', pelanggan_id: 0, limit: 5 }; },
    processResults: function (d) { return { results: d.results }; },
    cache: true,
  },
});
\$('#modalTambah').on('hidden.bs.modal', function () {
  \$('#selectSecretTambah').val(null).trigger('change');
});

var tabelPelanggan = \$('#tabelPelanggan').DataTable({
  serverSide: true,
  processing: true,
  searching: false,
  dom: 'rt<"d-flex justify-content-between align-items-center mt-2 flex-wrap"ip>',
  pageLength: 10,
  order: [[1, 'asc']],
  language: { emptyTable:'Tidak ada data',info:'Menampilkan _START_-_END_ dari _TOTAL_ entri',infoEmpty:'0 entri',infoFiltered:'(dari _MAX_ total)',lengthMenu:'Tampilkan _MENU_ entri',loadingRecords:'Memuat...',processing:'Memproses...',zeroRecords:'Data tidak ditemukan',paginate:{first:'Pertama',last:'Terakhir',next:'›',previous:'‹'} },
  ajax: {
    url: '{$base_url}modules/pelanggan/act.php',
    data: function (d) {
      d.action        = 'datatable';
      d.status_filter = \$('#filterStatus').val();
      d.area_filter   = \$('#filterArea').val();
      d.search.value  = \$('#filterSearch').val();
    }
  },
  columns: [
    { data: 'no', orderable: false },
    { data: 'nama' },
    { data: 'no_hp' },
    { data: 'alamat' },
    { data: 'area' },
    { data: 'paket' },
    { data: 'status' },
    { data: 'aksi', orderable: false },
  ],
});

\$('#btnCari').on('click', function () { tabelPelanggan.ajax.reload(); });
\$('#filterSearch').on('keyup', function (e) { if (e.key === 'Enter') tabelPelanggan.ajax.reload(); });
\$('#filterStatus').on('change', function () { tabelPelanggan.ajax.reload(); });
\$('#filterArea').on('change', function () { tabelPelanggan.ajax.reload(); });
\$('#btnReset').on('click', function () {
  \$('#filterSearch').val('');
  \$('#filterStatus').val('');
  \$('#filterArea').val('');
  tabelPelanggan.ajax.reload();
});
\$('#lengthPelanggan').on('change', function () {
  tabelPelanggan.page.len(parseInt(\$(this).val())).draw();
});

function rupiahFmtPl(n) {
  return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
}
function tglIndoPl(s) {
  if (!s) return '—';
  var bln = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
  var p = s.substring(0, 10).split('-');
  return p[2] + ' ' + bln[parseInt(p[1])] + ' ' + p[0];
}
function badgeStatusPl(status) {
  var map = { aktif: 'success', nonaktif: 'secondary', isolir: 'danger', lunas: 'success', belum: 'danger' };
  var label = { aktif: 'Aktif', nonaktif: 'Non-aktif', isolir: 'Isolir', lunas: 'Lunas', belum: 'Belum' };
  return '<span class="badge badge-' + (map[status] || 'secondary') + '">' + (label[status] || status) + '</span>';
}

$(document).on('click', '.btn-detail-pelanggan', function () {
  var id = $(this).data('id');
  $('#detailBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat data&hellip;</div>');

  $.getJSON('{$base_url}modules/pelanggan/act.php', { action: 'get_detail', id: id }, function (res) {
    if (!res.success) { $('#detailBody').html('<p class="text-danger p-3">Gagal memuat data.</p>'); return; }
    var d         = res.data.pelanggan;
    var riw       = res.data.riwayat;
    var statusLog = res.data.status_log || [];
    var esc = function(s) { return $('<div>').text(s || '').html(); };

    var riwayatRows = '';
    if (riw.length) {
      $.each(riw, function (i, r) {
        riwayatRows +=
          '<tr>' +
            '<td>' + esc(r.bulan_tagihan || '—') + '</td>' +
            '<td>' + rupiahFmtPl(r.jumlah) + '</td>' +
            '<td>' + (r.status === 'lunas' ? rupiahFmtPl(r.terbayar) : '<span class="text-muted">—</span>') + '</td>' +
            '<td>' + (parseInt(r.potongan) > 0 ? r.potongan + ' hari' : '<span class="text-muted">—</span>') + '</td>' +
            '<td>' + (r.status === 'lunas' ? (r.metode === 'transfer' ? '<span class="badge badge-info">Transfer</span>' : '<span class="badge badge-secondary">Tunai</span>') : '<span class="text-muted">—</span>') + '</td>' +
            '<td>' + tglIndoPl(r.tgl_bayar) + '</td>' +
            '<td>' + esc(r.nama_kasir || '—') + '</td>' +
            '<td>' + badgeStatusPl(r.status) + '</td>' +
          '</tr>';
      });
    } else {
      riwayatRows = '<tr><td colspan="8" class="text-center text-muted py-4">Belum ada riwayat pembayaran.</td></tr>';
    }

    var statusLogRows = '';
    if (statusLog.length) {
      $.each(statusLog, function (i, l) {
        var det = {};
        try { det = JSON.parse(l.details || '{}'); } catch (e) {}
        var perubahan;
        if (l.tipe === 'paket') {
          perubahan = '<span class="badge badge-info mr-1">Paket</span>' +
            esc(det.lama || '—') + ' &rarr; ' + esc(det.baru || '—');
        } else {
          perubahan = '<span class="badge badge-secondary mr-1">Status</span>' +
            (det.lama ? badgeStatusPl(det.lama) : '<span class="text-muted">Baru daftar</span>') + ' &rarr; ' + badgeStatusPl(det.baru);
        }
        statusLogRows +=
          '<tr>' +
            '<td>' + esc(l.created_at) + '</td>' +
            '<td>' + perubahan + '</td>' +
            '<td>' + esc(l.keterangan || '—') + '</td>' +
            '<td>' + esc(l.nama_user || '—') + '</td>' +
          '</tr>';
      });
    } else {
      statusLogRows = '<tr><td colspan="4" class="text-center text-muted py-4">Belum ada riwayat status.</td></tr>';
    }

    $('#detailBody').html(
      '<ul class="nav nav-tabs" id="detailTabs" role="tablist">' +
        '<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabInfo"><i class="fas fa-user mr-1" style="font-size:12px"></i>Info Pelanggan</a></li>' +
        '<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabRiwayat"><i class="fas fa-receipt mr-1" style="font-size:12px"></i>Riwayat Pembayaran</a></li>' +
        '<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabStatusLog"><i class="fas fa-history mr-1" style="font-size:12px"></i>Riwayat Aktivitas</a></li>' +
      '</ul>' +
      '<div class="tab-content pt-3">' +
        '<div class="tab-pane fade show active" id="tabInfo">' +
          '<table class="table table-sm">' +
            '<tr><th width="160">Nama</th><td>' + esc(d.nama) + '</td></tr>' +
            '<tr><th>No. HP</th><td>' + esc(d.no_hp) + '</td></tr>' +
            '<tr><th>Alamat</th><td>' + esc(d.alamat) + '</td></tr>' +
            '<tr><th>Area</th><td>' + esc(d.nama_area || '—') + '</td></tr>' +
            '<tr><th>Paket</th><td>' + esc(d.nama_paket || '—') + '</td></tr>' +
            '<tr><th>Username PPPoE</th><td>' + esc(d.username_pppoe || '—') + '</td></tr>' +
            '<tr><th>Tanggal Daftar</th><td>' + tglIndoPl(d.tgl_daftar) + '</td></tr>' +
            '<tr><th>Status Saat Ini</th><td>' + badgeStatusPl(d.status) + '</td></tr>' +
            '<tr><th>Keterangan</th><td>' + esc(d.keterangan || '—') + '</td></tr>' +
            '<tr><th>Device ONU</th><td>' + (
              d.genieacs_device_id
                ? esc(d.device_tag || '—') + ' <button type="button" class="btn btn-warning btn-xs ml-2 btn-reboot-onu" data-id="' + d.id + '"><i class="fas fa-power-off mr-1"></i>Reboot ONU</button>'
                : '<span class="text-muted">Belum dimapping — atur lewat menu ACS &gt; Device ONU</span>'
            ) + '</td></tr>' +
          '</table>' +
        '</div>' +
        '<div class="tab-pane fade" id="tabRiwayat">' +
          '<div class="table-responsive">' +
            '<table class="table table-sm table-hover">' +
              '<thead><tr>' +
                '<th>Bulan Tagihan</th><th>Jumlah</th><th>Terbayar</th><th>Potongan</th><th>Metode</th><th>Tgl Bayar</th><th>Kasir</th><th>Status</th>' +
              '</tr></thead>' +
              '<tbody>' + riwayatRows + '</tbody>' +
            '</table>' +
          '</div>' +
        '</div>' +
        '<div class="tab-pane fade" id="tabStatusLog">' +
          '<div class="table-responsive">' +
            '<table class="table table-sm table-hover">' +
              '<thead><tr><th>Tanggal</th><th>Perubahan</th><th>Keterangan</th><th>Oleh</th></tr></thead>' +
              '<tbody>' + statusLogRows + '</tbody>' +
            '</table>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
  });
});

$(document).on('click', '.btn-reboot-onu', function () {
  var \$btn = $(this);
  var id    = \$btn.data('id');
  if (!confirm('Yakin reboot ONU pelanggan ini? Koneksi internet akan putus sebentar.')) return;

  \$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Mengirim...');

  $.post('{$base_url}modules/pelanggan/act.php', {
    action: 'reboot_onu',
    id: id,
    csrf_token: $('input[name="csrf_token"]').first().val(),
  }, function (res) {
    toastr[res.success ? 'success' : 'error'](res.msg);
  }, 'json').always(function () {
    \$btn.prop('disabled', false).html('<i class="fas fa-power-off mr-1"></i>Reboot ONU');
  });
});

$(document).on('click', '.btn-edit-pelanggan', function () {
  var id = $(this).data('id');
  $('#edit_id').val(id);
  $('#editBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat data&hellip;</div>');

  $.getJSON('{$base_url}modules/pelanggan/act.php', { action: 'get_json', id: id }, function (res) {
    if (!res.success) { $('#editBody').html('<p class="text-danger p-3">Gagal memuat data.</p>'); return; }
    var d = res.data;

    var esc = function(s) { return $('<div>').text(s || '').html(); };

    var pakAktif = null;
    $.each(paketOptions, function(i, p) { if (p.id == d.paket_id) pakAktif = p; });
    var paketInfo = pakAktif
      ? esc(pakAktif.nama) + (pakAktif.rate_limit ? ' (' + esc(pakAktif.rate_limit) + ')' : '')
      : '<span class="text-muted">— Belum ada paket —</span>';

    var areaHtml = '<option value="">— Pilih Area —</option>';
    var areaGroupsJs = {};
    var areaGroupOrder = [];
    $.each(areaOptions, function(i, a) {
      var label = (a.keterangan && a.keterangan.trim() !== '') ? a.keterangan : 'Lainnya';
      if (!areaGroupsJs[label]) { areaGroupsJs[label] = []; areaGroupOrder.push(label); }
      areaGroupsJs[label].push(a);
    });
    $.each(areaGroupOrder, function(i, label) {
      areaHtml += '<optgroup label="' + esc(label) + '">';
      $.each(areaGroupsJs[label], function(j, a) {
        areaHtml += '<option value="' + a.id + '"' + (a.id == d.area_id ? ' selected' : '') + '>' + esc(a.nama) + '</option>';
      });
      areaHtml += '</optgroup>';
    });

    // Secret PPP: siapkan option pre-select jika sudah ada
    var secretHtml = '<option value="">— Pilih Secret PPP —</option>';
    if (d.mikrotik_secrets_id) {
      // cari nama secret dari secretOptions untuk pre-fill
      var curSecret = secretOptions.find(function(sc) { return sc.id == d.mikrotik_secrets_id; });
      var curLabel  = curSecret ? esc(curSecret.name) + (curSecret.profile ? ' — profile: ' + esc(curSecret.profile) : '') : 'Secret #' + d.mikrotik_secrets_id;
      secretHtml += '<option value="' + d.mikrotik_secrets_id + '" selected>' + curLabel + '</option>';
    }

    $('#editBody').html(
      '<div class="row">' +
        '<div class="col-md-12"><div class="form-group">' +
          '<label class="form-label">Secret PPP <span class="text-danger">*</span></label>' +
          '<select name="mikrotik_secrets_id" class="form-control" required>' + secretHtml + '</select>' +
        '</div></div>' +
        '<div class="col-md-6"><div class="form-group">' +
          '<label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>' +
          '<input type="text" name="nama" class="form-control" value="' + esc(d.nama) + '" required>' +
        '</div></div>' +
        '<div class="col-md-6"><div class="form-group">' +
          '<label class="form-label">No. HP</label>' +
          '<input type="text" name="no_hp" class="form-control" value="' + esc(d.no_hp) + '">' +
        '</div></div>' +
        '<div class="col-md-12"><div class="form-group">' +
          '<label class="form-label">Alamat</label>' +
          '<textarea name="alamat" class="form-control" rows="2">' + esc(d.alamat) + '</textarea>' +
        '</div></div>' +
        '<div class="col-md-6"><div class="form-group">' +
          '<label class="form-label">Area</label>' +
          '<select name="area_id" class="form-control">' + areaHtml + '</select>' +
        '</div></div>' +
        '<div class="col-md-12"><div class="form-group">' +
          '<label class="form-label">Paket Internet</label>' +
          '<div>' + paketInfo + '</div>' +
          '<small class="text-muted">Upgrade/downgrade paket dilakukan lewat menu <a href="{$base_url}modules/pelanggan/status.php">Status Pelanggan</a>.</small>' +
        '</div></div>' +
        '<div class="col-md-6"><div class="form-group">' +
          '<label class="form-label">Status</label>' +
          '<select name="status" class="form-control">' +
            '<option value="aktif"'    + (d.status === 'aktif'    ? ' selected' : '') + '>Aktif</option>' +
            '<option value="nonaktif"' + (d.status === 'nonaktif' ? ' selected' : '') + '>Non-aktif</option>' +
            '<option value="isolir"'   + (d.status === 'isolir'   ? ' selected' : '') + '>Isolir</option>' +
          '</select>' +
        '</div></div>' +
        '<div class="col-md-12"><div class="form-group">' +
          '<label class="form-label">Keterangan</label>' +
          '<textarea name="keterangan" class="form-control" rows="2">' + esc(d.keterangan) + '</textarea>' +
        '</div></div>' +
      '</div>'
    );

    // Select2 AJAX — Secret PPP modal Edit
    var pelId = d.id;
    \$('#editBody select[name="mikrotik_secrets_id"]').select2({
      dropdownParent: \$('#modalEdit'),
      placeholder: '— Pilih Secret PPP —',
      width: '100%',
      minimumInputLength: 0,
      ajax: {
        url: '{$base_url}api/search_mikrotik_secret.php',
        dataType: 'json',
        delay: 250,
        data: function (p) { return { q: p.term || '', pelanggan_id: pelId, limit: 5 }; },
        processResults: function (d) { return { results: d.results }; },
        cache: false,
      },
    });
  });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
