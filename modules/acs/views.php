<?php
// ============================================================
//  KAHFINET - Modul ACS / Pengaturan Koneksi (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN]);

$acs = db_row("SELECT * FROM acs_settings WHERE is_active = 1 ORDER BY id LIMIT 1");

$page_title  = 'ACS — Pengaturan';
$active_menu = 'acs_pengaturan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-satellite-dish mr-2 text-primary"></i>Pengaturan ACS</h5>
</div>

<div class="row">
  <div class="col-lg-7 mb-4">
    <div class="card">
      <div class="card-header">
        <span><i class="fas fa-server mr-2"></i>Koneksi GenieACS (NBI API)</span>
      </div>
      <div class="card-body">
        <p class="text-muted" style="font-size:13px">
          Alamat ini dipakai untuk sinkronisasi daftar device ONU/ONT dan kirim perintah reboot.
          Pastikan ini alamat <strong>NBI API</strong> GenieACS (biasanya port <code>7557</code>), bukan alamat CWMP yang dipakai device (port <code>7547</code>).
        </p>
        <form method="POST" action="<?= BASE_URL ?>modules/acs/act.php" id="formAcs">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save_settings">

          <div class="form-group">
            <label class="form-label">Nama</label>
            <input type="text" name="nama" class="form-control"
              value="<?= clean($acs['nama'] ?? 'ACS Utama') ?>" maxlength="100">
          </div>

          <div class="form-group">
            <label class="form-label">Base URL <span class="text-danger">*</span></label>
            <input type="text" name="base_url" class="form-control" placeholder="http://192.168.10.107:7557"
              value="<?= clean($acs['base_url'] ?? '') ?>" maxlength="255" required>
          </div>

          <div class="form-group">
            <label class="form-label">Username (opsional)</label>
            <input type="text" name="username" class="form-control"
              value="<?= clean($acs['username'] ?? '') ?>" maxlength="100"
              placeholder="Biarkan kosong kalau NBI tidak pakai autentikasi">
          </div>

          <div class="form-group">
            <label class="form-label">Password (opsional)</label>
            <input type="password" name="password" class="form-control"
              placeholder="Biarkan kosong jika tidak ingin mengubah password" autocomplete="new-password">
          </div>

          <div class="d-flex" style="gap:8px">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fas fa-save mr-1"></i>Simpan
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestConn">
              <i class="fas fa-plug mr-1"></i>Tes Koneksi
            </button>
          </div>
          <div id="testConnResult" class="mt-3" style="font-size:13px"></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5 mb-4">
    <div class="card">
      <div class="card-header">
        <span><i class="fas fa-info-circle mr-2"></i>Catatan Integrasi</span>
      </div>
      <div class="card-body" style="font-size:13px">
        <ul class="pl-3 mb-0">
          <li class="mb-2">Mapping device ONU ke pelanggan dilakukan <strong>otomatis</strong> lewat parameter <code>VirtualParameters.pppoeUsername</code> di device, dicocokkan dengan username PPPoE (Secret) pelanggan — tidak perlu input device ID manual.</li>
          <li class="mb-2">Sync daftar device dilakukan dari menu ACS &gt; Device ONU.</li>
          <li>Kalau ACS sedang tidak bisa dihubungi, fitur lain (status, pembayaran, dll) tetap berjalan normal — cuma fitur reboot ONU yang kena pengaruh.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
$content  = ob_get_clean();
$base_url = BASE_URL;
$extra_js = <<<HTML
<script>
\$('#btnTestConn').on('click', function() {
  var \$btn = \$(this);
  var \$out = \$('#testConnResult');
  \$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menghubungkan...');
  \$out.html('');

  \$.post('{$base_url}modules/acs/act.php?action=test_connection', \$('#formAcs').serialize())
    .done(function(res) {
      \$out.html('<span class="text-' + (res.success ? 'success' : 'danger') + '">' +
        '<i class="fas fa-' + (res.success ? 'check-circle' : 'times-circle') + ' mr-1"></i>' +
        res.msg + '</span>');
    })
    .fail(function() {
      \$out.html('<span class="text-danger"><i class="fas fa-times-circle mr-1"></i>Gagal menghubungi server.</span>');
    })
    .always(function() {
      \$btn.prop('disabled', false).html('<i class="fas fa-plug mr-1"></i>Tes Koneksi');
    });
});
</script>
HTML;

require_once __DIR__ . '/../../template.php';
