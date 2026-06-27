<?php
// ============================================================
//  KAHFINET - Modul Status Pelanggan (Tampilan)
//  Halaman formulir untuk mengubah status pelanggan (aktif/
//  nonaktif/isolir) cepat tanpa harus buka form edit penuh.
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_TEKNISI]);

$pelanggans = db_rows(
  "SELECT pl.id, pl.nama, pl.no_hp, pl.status, pl.paket_id, ar.nama as nama_area
   FROM pelanggan pl
   LEFT JOIN area ar ON ar.id = pl.area_id
   ORDER BY pl.nama"
);
$pakets = db_rows(
  "SELECT pk.id, pk.nama, mpc.rate_limit
   FROM paket pk
   LEFT JOIN mikrotik_profiles_cache mpc ON mpc.id = pk.mikrotik_profiles_id
   WHERE pk.status='aktif' AND pk.nama != 'Isolir Pelanggan'
   ORDER BY pk.kecepatan ASC"
);

$page_title  = 'Status Pelanggan';
$active_menu = 'status_pelanggan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-toggle-on mr-2 text-primary"></i>Status Pelanggan</h5>
</div>

<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <span><i class="fas fa-edit mr-2"></i>Ubah Status Pelanggan</span>
      </div>
      <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>modules/pelanggan/act.php" id="formStatus">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="update_status">
          <div class="form-group">
            <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
            <select name="id" id="selectPelangganStatus" class="form-control" required style="width:100%">
              <option value="">— Cari Pelanggan —</option>
              <?php foreach ($pelanggans as $pl): ?>
                <option value="<?= $pl['id'] ?>" data-status="<?= $pl['status'] ?>" data-paket-id="<?= (int)$pl['paket_id'] ?>">
                  <?= clean($pl['nama']) ?><?= $pl['no_hp'] ? ' — ' . clean($pl['no_hp']) : '' ?><?= $pl['nama_area'] ? ' (' . clean($pl['nama_area']) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status Saat Ini</label>
            <div id="statusSaatIni"><span class="text-muted">— Pilih pelanggan dulu —</span></div>
          </div>
          <div class="form-group">
            <label class="form-label">Status Baru <span class="text-danger">*</span></label>
            <select name="status" id="selectStatusBaru" class="form-control" required>
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Non-aktif</option>
              <option value="isolir">Isolir</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Paket Internet</label>
            <select name="paket_id" id="selectPaketBaru" class="form-control">
              <option value="">— Tidak diubah —</option>
              <?php foreach ($pakets as $pk): ?>
                <option value="<?= $pk['id'] ?>">
                  <?= clean($pk['nama']) ?><?= $pk['rate_limit'] ? ' (' . clean($pk['rate_limit']) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Paket yang sedang berjalan otomatis terpilih. Ganti ke paket lain untuk upgrade/downgrade.</small>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan</label>
            <textarea name="keterangan" class="form-control" rows="3"
              placeholder="Informasi tambahan perubahan status (opsional), cth: nunggak 2 bulan, sudah lapor mau berhenti, dll."></textarea>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i>Simpan Perubahan Status
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$content  = ob_get_clean();
$base_url = BASE_URL;

$extra_js = <<<HTML
<script>
\$('#selectPelangganStatus').select2({
  theme: 'bootstrap',
  placeholder: '— Cari Pelanggan —',
  width: '100%',
});

function tampilkanStatusSaatIni() {
  var \$opt = \$('#selectPelangganStatus').find(':selected');
  var status = \$opt.data('status');
  var map = { aktif: 'success', nonaktif: 'secondary', isolir: 'danger' };
  var label = { aktif: 'Aktif', nonaktif: 'Non-aktif', isolir: 'Isolir' };
  if (status) {
    \$('#statusSaatIni').html('<span class="badge badge-' + map[status] + '">' + label[status] + '</span>');
    \$('#selectStatusBaru').val(status);
  } else {
    \$('#statusSaatIni').html('<span class="text-muted">— Pilih pelanggan dulu —</span>');
  }

  var paketId = \$opt.data('paket-id');
  var \$paketOpt = \$('#selectPaketBaru option[value="' + paketId + '"]');
  \$('#selectPaketBaru').val(\$paketOpt.length ? paketId : '');
}

\$('#selectPelangganStatus').on('change', tampilkanStatusSaatIni);

\$('#formStatus').on('submit', function (e) {
  var \$opt      = \$('#selectPelangganStatus').find(':selected');
  var oldStatus  = \$opt.data('status');
  var newStatus  = \$('#selectStatusBaru').val();
  if (newStatus === 'isolir' && oldStatus !== 'isolir' &&
      !confirm('Yakin isolir pelanggan ' + \$opt.text().trim() + '?')) {
    e.preventDefault();
  }
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
