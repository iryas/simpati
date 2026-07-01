<?php
// ============================================================
//  KAHFINET - Modul Pembayaran (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KASIR]);

$search = get('search');
$status = get('status');
$bulan  = get('bulan', date('Y-m'));

$pelanggans = db_rows("SELECT id, nama, no_hp, paket_id FROM pelanggan WHERE status='aktif' ORDER BY nama");
$petugas    = db_rows("SELECT id, nama FROM pengguna WHERE role IN ('admin','kasir') AND status='aktif' ORDER BY nama");

$page_title  = 'Pembayaran';
$active_menu = 'pembayaran';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-money-bill-wave mr-2 text-primary"></i>Data Pembayaran</h5>
  <div class="d-flex" style="gap:8px">
    <form method="POST" action="<?= BASE_URL ?>modules/pembayaran/act.php" id="formGenerate" class="d-inline">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="generate">
      <input type="hidden" name="bulan" value="<?= clean($bulan) ?>">
      <input type="hidden" name="ret_search" value="<?= clean($search) ?>">
      <input type="hidden" name="ret_status" value="<?= clean($status) ?>">
      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-bolt mr-1"></i>Generate Pembayaran
      </button>
    </form>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
      <i class="fas fa-plus mr-1"></i>Catat Pembayaran
    </button>
  </div>
</div>

<!-- Summary bulan ini -->
<?php
$sum = db_row(
    "SELECT COALESCE(SUM(CASE WHEN status='lunas' THEN terbayar ELSE 0 END),0) as lunas,
            COUNT(CASE WHEN status='lunas' THEN 1 END) as n_lunas,
            COUNT(CASE WHEN status='belum' THEN 1 END) as n_belum
     FROM pembayaran WHERE bulan_tagihan = ?",
    [$bulan]
);
?>
<div class="row mb-3">
  <div class="col-md-4 mb-2">
    <div class="card border-left-success" style="border-left:4px solid #10b981">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Total Lunas Bulan <?= date('M Y', strtotime($bulan.'-01')) ?></div>
        <div class="font-weight-bold text-success" style="font-size:18px">
          <?= rupiah((int)$sum['lunas']) ?>
        </div>
        <small class="text-muted"><?= $sum['n_lunas'] ?> melunasi</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card" style="border-left:4px solid #ef4444">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Belum Bayar</div>
        <div class="font-weight-bold text-danger" style="font-size:18px"><?= $sum['n_belum'] ?> tagihan</div>
        <small class="text-muted">Bulan <?= date('M Y', strtotime($bulan.'-01')) ?></small>
      </div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="form-inline flex-wrap" style="gap:8px">
      <input type="month" name="bulan" class="form-control form-control-sm" value="<?= $bulan ?>">
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Cari pelanggan…" value="<?= clean($search) ?>" style="min-width:180px">
      <select name="status" class="form-control form-control-sm">
        <option value="">Semua Status</option>
        <option value="lunas" <?= $status==='lunas'?'selected':'' ?>>Lunas</option>
        <option value="belum" <?= $status==='belum'?'selected':'' ?>>Belum Bayar</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-search mr-1"></i>Filter
      </button>
      <a href="<?= BASE_URL ?>modules/pembayaran/views.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
  </div>
</div>

<!-- Tabel -->
<div class="card">
  <div class="card-body p-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:8px">
      <div class="d-flex align-items-center" style="gap:6px">
        <label class="mb-0 text-muted" style="font-size:13px">Tampilkan</label>
        <select id="lengthPembayaran" class="form-control form-control-sm" style="width:70px">
          <option value="15">15</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <label class="mb-0 text-muted" style="font-size:13px">entri</label>
      </div>
      <div style="display:none" id="aksiMassalGroup">
        <button type="button" id="btnPotonganMassal" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-percent mr-1"></i>Potongan Massal (<span id="jumlahTerpilihPotongan">0</span> terpilih)
        </button>
        <button type="button" id="btnBayarMassal" class="btn btn-success btn-sm">
          <i class="fas fa-money-check-alt mr-1"></i>Bayar Massal (<span id="jumlahTerpilih">0</span> terpilih)
        </button>
      </div>
    </div>
    <div class="table-responsive">
      <table id="tabelPembayaran" class="table table-hover mb-0 w-100">
        <thead>
          <tr>
            <th><input type="checkbox" id="chkAllBayarMassal" title="Pilih semua (tagihan belum bayar)"></th>
            <th>#</th>
            <th>Pelanggan</th>
            <th>Jumlah</th>
            <th>Terbayar</th>
            <th>Potongan</th>
            <th>Tgl Bayar</th>
            <th>Kasir</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tambah Pembayaran -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus mr-2"></i>Catat Pembayaran</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pembayaran/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="ret_bulan" value="<?= clean($bulan) ?>">
        <input type="hidden" name="ret_search" value="<?= clean($search) ?>">
        <input type="hidden" name="ret_status" value="<?= clean($status) ?>">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
            <select name="pelanggan_id" class="form-control" id="selectPelanggan" required>
              <option value="">— Pilih Pelanggan —</option>
              <?php foreach ($pelanggans as $pl): ?>
              <option value="<?= $pl['id'] ?>" data-paket="<?= $pl['paket_id'] ?>">
                <?= clean($pl['nama']) ?> (<?= clean($pl['no_hp'] ?? '') ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Bulan Tagihan <span class="text-danger">*</span></label>
            <input type="month" name="bulan_tagihan" class="form-control" value="<?= date('Y-m') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nominal Tagihan (Rp) <span class="text-danger">*</span></label>
            <input type="number" name="jumlah" id="inputJumlah" class="form-control" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="inputStatus" class="form-control">
              <option value="lunas">Lunas</option>
              <option value="belum">Belum Bayar</option>
            </select>
          </div>
          <div id="blokPotonganTambah">
            <div class="form-group">
              <label class="form-label">Potongan (hari)</label>
              <input type="number" name="potongan" id="inputPotongan" class="form-control" min="0" max="30" value="0">
              <small class="text-muted">Maks. 30 hari (1 bulan penuh).</small>
            </div>
            <div class="form-group">
              <label class="form-label">Terbayar (Rp)</label>
              <input type="text" class="form-control" id="inputTerbayarDisplay">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Tgl Bayar</label>
            <input type="date" name="tgl_bayar" id="inputTglBayar" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Petugas/Kasir <span class="text-danger">*</span></label>
            <select name="kasir_id" class="form-control" required>
              <?php foreach ($petugas as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id'] == current_user()['id'] ? 'selected' : '' ?>>
                  <?= clean($p['nama']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan</label>
            <input type="text" name="keterangan" class="form-control">
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

<!-- Modal Bayar -->
<div class="modal fade" id="modalBayar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-money-bill mr-2"></i>Bayar Tagihan</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pembayaran/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="bayar">
        <input type="hidden" name="ret_bulan" value="<?= clean($bulan) ?>">
        <input type="hidden" name="ret_search" value="<?= clean($search) ?>">
        <input type="hidden" name="ret_status" value="<?= clean($status) ?>">
        <input type="hidden" name="id" id="bayar_id">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Pelanggan</label>
            <input type="text" class="form-control" id="bayar_nama" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Nominal Tagihan (Rp)</label>
            <input type="text" class="form-control" id="bayar_jumlah_display" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Potongan (hari)</label>
            <input type="number" name="potongan" id="bayar_potongan" class="form-control" min="0" max="30" value="0">
            <small class="text-muted">Maks. 30 hari (1 bulan penuh).</small>
          </div>
          <div class="form-group">
            <label class="form-label">Terbayar (Rp)</label>
            <input type="text" name="terbayar_display" class="form-control" id="bayar_terbayar_display">
          </div>
          <div class="form-group">
            <label class="form-label">Petugas/Kasir <span class="text-danger">*</span></label>
            <select name="kasir_id" id="bayar_kasir" class="form-control" required>
              <?php foreach ($petugas as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id'] == current_user()['id'] ? 'selected' : '' ?>>
                  <?= clean($p['nama']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-sm">
            <i class="fas fa-check mr-1"></i>Konfirmasi Bayar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Atur Potongan (sebelum lunas, status tetap Belum) -->
<div class="modal fade" id="modalPotongan" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-percent mr-2"></i>Atur Potongan</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pembayaran/act.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="set_potongan">
        <input type="hidden" name="ret_bulan" value="<?= clean($bulan) ?>">
        <input type="hidden" name="ret_search" value="<?= clean($search) ?>">
        <input type="hidden" name="ret_status" value="<?= clean($status) ?>">
        <input type="hidden" name="id" id="potongan_id">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Pelanggan</label>
            <input type="text" class="form-control" id="potongan_nama" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Nominal Tagihan (Rp)</label>
            <input type="text" class="form-control" id="potongan_jumlah_display" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Potongan (hari)</label>
            <input type="number" name="potongan" id="potongan_input" class="form-control" min="0" max="30" value="0">
            <small class="text-muted">Cuma disimpan sebagai rencana — status tagihan tetap "Belum" sampai beneran dibayar lewat tombol Bayar.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Potongan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Potongan Massal (rencana, status tetap Belum) -->
<div class="modal fade" id="modalPotonganMassal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-percent mr-2"></i>Potongan Massal</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pembayaran/act.php" id="formPotonganMassal">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="set_potongan_massal">
        <input type="hidden" name="ret_bulan" value="<?= clean($bulan) ?>">
        <input type="hidden" name="ret_search" value="<?= clean($search) ?>">
        <input type="hidden" name="ret_status" value="<?= clean($status) ?>">
        <div id="massalPotonganIdsContainer"></div>
        <div class="modal-body">
          <p class="mb-2"><span id="massalPotonganJumlahTagihan" class="font-weight-bold">0</span> tagihan akan diatur potongannya. Status tetap <span class="text-danger font-weight-bold">Belum</span> sampai dibayar.</p>
          <div class="form-group">
            <label class="form-label">Potongan (hari)</label>
            <input type="number" name="potongan" id="massal_potongan_input" class="form-control" min="0" max="30" value="0">
            <small class="text-muted">Diterapkan sama ke semua tagihan terpilih (dihitung per nominal masing-masing). Maks. 30 hari.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Potongan Massal
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Bayar Massal -->
<div class="modal fade" id="modalBayarMassal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-money-check-alt mr-2"></i>Bayar Massal</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pembayaran/act.php" id="formBayarMassal">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="bayar_massal">
        <input type="hidden" name="ret_bulan" value="<?= clean($bulan) ?>">
        <input type="hidden" name="ret_search" value="<?= clean($search) ?>">
        <input type="hidden" name="ret_status" value="<?= clean($status) ?>">
        <div id="massalIdsContainer"></div>
        <div class="modal-body">
          <p class="mb-2"><span id="massalJumlahTagihan" class="font-weight-bold">0</span> tagihan akan ditandai <span class="text-success font-weight-bold">Lunas</span>.</p>
          <p class="text-muted" style="font-size:12.5px">Potongan dipakai dari yang sudah diatur sebelumnya per tagihan (tombol "Atur Potongan"). Tagihan tanpa potongan terjadwal akan dianggap lunas penuh.</p>
          <div class="form-group">
            <label class="form-label">Petugas/Kasir <span class="text-danger">*</span></label>
            <select name="kasir_id" id="massal_kasir" class="form-control" required>
              <?php foreach ($petugas as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id'] == current_user()['id'] ? 'selected' : '' ?>>
                  <?= clean($p['nama']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-sm">
            <i class="fas fa-check mr-1"></i>Proses Bayar Massal
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit Pembayaran (admin saja, untuk yang sudah lunas) -->
<div class="modal fade" id="modalEditBayar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Pembayaran</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" action="<?= BASE_URL ?>modules/pembayaran/act.php" id="formEditBayar">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="ret_bulan" value="<?= clean($bulan) ?>">
        <input type="hidden" name="ret_search" value="<?= clean($search) ?>">
        <input type="hidden" name="ret_status" value="<?= clean($status) ?>">
        <input type="hidden" name="id" id="edit_bayar_id">
        <div class="modal-body" id="editBayarBody">
          <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat data&hellip;</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan
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
$pakets_json  = json_encode(array_column(db_rows("SELECT id, harga FROM paket"), 'harga', 'id'));
$petugas_json = json_encode(array_map(fn($p) => ['id' => $p['id'], 'nama' => $p['nama']], $petugas));
$bulan_json   = json_encode($bulan);
$search_json  = json_encode($search);
$status_json  = json_encode($status);
$today        = date('Y-m-d');

$extra_js = <<<HTML
<script>
var tabelPembayaran = \$('#tabelPembayaran').DataTable({
  serverSide: true,
  processing: true,
  searching: false,
  dom: 'rt<"d-flex justify-content-between align-items-center mt-2 flex-wrap"ip>',
  pageLength: 15,
  order: [[0, 'desc']],
  language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json' },
  ajax: {
    url: '{$base_url}modules/pembayaran/act.php',
    data: function (d) {
      d.action        = 'datatable';
      d.bulan         = {$bulan_json};
      d.q             = {$search_json};
      d.status_filter = {$status_json};
    }
  },
  columns: [
    { data: 'checkbox', orderable: false },
    { data: 'no', orderable: false },
    { data: 'pelanggan', orderable: false },
    { data: 'jumlah' },
    { data: 'terbayar' },
    { data: 'potongan' },
    { data: 'tgl_bayar' },
    { data: 'kasir', orderable: false },
    { data: 'status' },
    { data: 'aksi', orderable: false },
  ],
});

\$('#lengthPembayaran').on('change', function () {
  tabelPembayaran.page.len(parseInt(\$(this).val())).draw();
});

// ── Bayar Massal & Potongan Massal: checkbox per baris + pilih semua (di halaman aktif) ──
function refreshBayarMassalBar() {
  const n = \$('.chk-bayar-massal:checked').length;
  \$('#jumlahTerpilih').text(n);
  \$('#jumlahTerpilihPotongan').text(n);
  \$('#aksiMassalGroup').toggle(n > 0);
  \$('#chkAllBayarMassal').prop('checked', n > 0 && n === \$('.chk-bayar-massal').length);
}

\$(document).on('change', '.chk-bayar-massal', refreshBayarMassalBar);

\$('#chkAllBayarMassal').on('change', function () {
  \$('.chk-bayar-massal').prop('checked', \$(this).is(':checked'));
  refreshBayarMassalBar();
});

// Checkbox per baris hilang tiap redraw DataTable (AJAX), reset bar-nya
tabelPembayaran.on('draw', function () {
  \$('#chkAllBayarMassal').prop('checked', false);
  refreshBayarMassalBar();
});

\$('#btnBayarMassal').on('click', function () {
  const ids = \$('.chk-bayar-massal:checked').map(function () { return \$(this).val(); }).get();
  if (!ids.length) return;

  let hidden = '';
  ids.forEach(function (id) { hidden += '<input type="hidden" name="ids[]" value="' + id + '">'; });
  \$('#massalIdsContainer').html(hidden);
  \$('#massalJumlahTagihan').text(ids.length);
  \$('#modalBayarMassal').modal('show');
});

\$('#formBayarMassal').on('submit', function (e) {
  const n = \$('#massalIdsContainer input').length;
  if (!confirm('Tandai lunas ' + n + ' tagihan terpilih?')) {
    e.preventDefault();
  }
});

\$('#btnPotonganMassal').on('click', function () {
  const ids = \$('.chk-bayar-massal:checked').map(function () { return \$(this).val(); }).get();
  if (!ids.length) return;

  let hidden = '';
  ids.forEach(function (id) { hidden += '<input type="hidden" name="ids[]" value="' + id + '">'; });
  \$('#massalPotonganIdsContainer').html(hidden);
  \$('#massalPotonganJumlahTagihan').text(ids.length);
  \$('#massal_potongan_input').val(0);
  \$('#modalPotonganMassal').modal('show');
});

\$('#formPotonganMassal').on('submit', function (e) {
  const n = \$('#massalPotonganIdsContainer input').length;
  const hari = parseInt(\$('#massal_potongan_input').val()) || 0;
  if (!confirm('Atur potongan ' + hari + ' hari untuk ' + n + ' tagihan terpilih?')) {
    e.preventDefault();
  }
});

// Auto-isi jumlah dari harga paket pelanggan
const pakets = {$pakets_json};
\$('#selectPelanggan').on('change', function() {
  const paketId = \$(this).find(':selected').data('paket');
  if (paketId && pakets[paketId]) {
    \$('#inputJumlah').val(pakets[paketId]);
    hitungTerbayarTambah();
  }
});

// Modal Catat Pembayaran: tampilkan blok potongan/terbayar hanya jika status Lunas
function hitungTerbayarTambah() {
  const jumlah    = parseInt(\$('#inputJumlah').val()) || 0;
  const potongan  = Math.max(0, Math.min(30, parseInt(\$('#inputPotongan').val()) || 0));
  const ha        = jumlah / 30;
  const terbayar  = jumlah - (ha * potongan);
  \$('#inputTerbayarDisplay').val(rupiahFmt(terbayar));
}

function toggleBlokPotonganTambah() {
  const isLunas = \$('#inputStatus').val() === 'lunas';
  \$('#blokPotonganTambah').toggle(isLunas);
  \$('#inputTglBayar').closest('.form-group').toggle(isLunas);
  if (isLunas) hitungTerbayarTambah();
}

\$('#inputStatus').on('change', toggleBlokPotonganTambah);
\$('#inputJumlah, #inputPotongan').on('input', hitungTerbayarTambah);
\$('#modalTambah').on('show.bs.modal', function() {
  \$('#inputPotongan').val(0);
  \$('#inputTglBayar').val('{$today}');
  toggleBlokPotonganTambah();
});

// Konfirmasi sebelum generate tagihan massal
\$('#formGenerate').on('submit', function(e) {
  if (!confirm('Generate tagihan untuk semua pelanggan aktif bulan ini? Pelanggan yang sudah ada tagihan di bulan ini akan dilewati.')) {
    e.preventDefault();
  }
});

// Modal Bayar: isi nominal tagihan + hitung terbayar live (rumus sama dengan server)
let bayarJumlah = 0;

function rupiahFmt(n) {
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function hitungTerbayar() {
  const potongan = Math.max(0, Math.min(30, parseInt(\$('#bayar_potongan').val()) || 0));
  const ha = bayarJumlah / 30;
  const terbayar = bayarJumlah - (ha * potongan);
  \$('#bayar_terbayar_display').val(rupiahFmt(terbayar));
}

\$(document).on('click', '.btn-bayar', function() {
  bayarJumlah = parseInt(\$(this).data('jumlah')) || 0;
  \$('#bayar_id').val(\$(this).data('id'));
  \$('#bayar_nama').val(\$(this).data('nama'));
  \$('#bayar_jumlah_display').val(rupiahFmt(bayarJumlah));
  \$('#bayar_potongan').val(parseInt(\$(this).data('potongan')) || 0);
  hitungTerbayar();
});

\$('#bayar_potongan').on('input', hitungTerbayar);

// Modal Atur Potongan (status tetap Belum, cuma simpan rencana potongan)
\$(document).on('click', '.btn-atur-potongan', function() {
  \$('#potongan_id').val(\$(this).data('id'));
  \$('#potongan_nama').val(\$(this).data('nama'));
  \$('#potongan_jumlah_display').val(rupiahFmt(parseInt(\$(this).data('jumlah')) || 0));
  \$('#potongan_input').val(parseInt(\$(this).data('potongan')) || 0);
});

// Modal Edit Pembayaran (admin saja)
const petugasOptions = {$petugas_json};

function hitungTerbayarEdit() {
  const jumlah    = parseInt(\$('#edit_bayar_jumlah').val()) || 0;
  const potongan  = Math.max(0, Math.min(30, parseInt(\$('#edit_bayar_potongan').val()) || 0));
  const ha        = jumlah / 30;
  const terbayar  = jumlah - (ha * potongan);
  \$('#edit_bayar_terbayar').val(Math.round(terbayar));
}

\$(document).on('click', '.btn-edit-bayar', function() {
  const id = \$(this).data('id');
  \$('#edit_bayar_id').val(id);
  \$('#editBayarBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat data&hellip;</div>');

  \$.getJSON('{$base_url}modules/pembayaran/act.php', { action: 'get_json', id: id }, function(res) {
    if (!res.success) { \$('#editBayarBody').html('<p class="text-danger p-3">Gagal memuat data.</p>'); return; }
    const d = res.data;

    let kasirHtml = '';
    \$.each(petugasOptions, function(i, p) {
      kasirHtml += '<option value="' + p.id + '"' + (p.id == d.kasir_id ? ' selected' : '') + '>' +
        \$('<div>').text(p.nama).html() + '</option>';
    });

    \$('#editBayarBody').html(
      '<div class="form-group">' +
        '<label class="form-label">Nominal Tagihan (Rp) <span class="text-danger">*</span></label>' +
        '<input type="number" name="jumlah" id="edit_bayar_jumlah" class="form-control" min="0" value="' + parseInt(d.jumlah) + '" required>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Potongan (hari)</label>' +
        '<input type="number" name="potongan" id="edit_bayar_potongan" class="form-control" min="0" max="30" value="' + parseInt(d.potongan) + '">' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Terbayar (Rp)</label>' +
        '<input type="number" name="terbayar" id="edit_bayar_terbayar" class="form-control" min="0" value="' + parseInt(d.terbayar) + '">' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Tanggal Bayar</label>' +
        '<input type="date" name="tgl_bayar" class="form-control" value="' + (d.tgl_bayar || '') + '">' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Petugas/Kasir <span class="text-danger">*</span></label>' +
        '<select name="kasir_id" class="form-control" required>' + kasirHtml + '</select>' +
      '</div>' +
      '<div class="form-group">' +
        '<label class="form-label">Keterangan</label>' +
        '<input type="text" name="keterangan" class="form-control" value="' + \$('<div>').text(d.keterangan || '').html() + '">' +
      '</div>'
    );

    \$('#edit_bayar_jumlah, #edit_bayar_potongan').on('input', hitungTerbayarEdit);
  });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
