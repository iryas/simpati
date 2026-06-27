<?php
// ============================================================
//  KAHFINET - Modul Mikrotik / Pengaturan Koneksi (Tampilan)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_role([ROLE_ADMIN]);

$router = db_row("SELECT * FROM mikrotik_routers WHERE is_active = 1 ORDER BY id LIMIT 1");

$page_title  = 'Mikrotik — Pengaturan';
$active_menu = 'mikrotik_pengaturan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-network-wired mr-2 text-primary"></i>Pengaturan Mikrotik</h5>
</div>

<div class="row">
  <div class="col-lg-7 mb-4">
    <div class="card">
      <div class="card-header">
        <span><i class="fas fa-server mr-2"></i>Koneksi RouterOS API</span>
      </div>
      <div class="card-body">
        <p class="text-muted" style="font-size:13px">
          Kredensial ini dipakai untuk sinkronisasi PPP profile (dari Paket) dan PPP secret (dari Pelanggan) ke Mikrotik.
          Gunakan user API dengan hak akses terbatas (cukup grup <code>read,write</code> di menu PPP), bukan akun admin penuh.
        </p>
        <form method="POST" action="<?= BASE_URL ?>modules/mikrotik/act.php" id="formRouter">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save_router">

          <div class="form-group">
            <label class="form-label">Nama Router</label>
            <input type="text" name="nama" class="form-control"
              value="<?= clean($router['nama'] ?? 'Router Utama') ?>" maxlength="100">
          </div>

          <div class="form-row">
            <div class="form-group col-md-8">
              <label class="form-label">Host / IP <span class="text-danger">*</span></label>
              <input type="text" name="host" class="form-control" placeholder="192.168.1.1"
                value="<?= clean($router['host'] ?? '') ?>" maxlength="100" required>
            </div>
            <div class="form-group col-md-4">
              <label class="form-label">Port API</label>
              <input type="number" name="api_port" class="form-control"
                value="<?= (int)($router['api_port'] ?? 8728) ?>" min="1" max="65535">
            </div>
          </div>

          <div class="form-group custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="use_ssl" name="use_ssl"
              <?= !empty($router['use_ssl']) ? 'checked' : '' ?>>
            <label class="custom-control-label" for="use_ssl">
              Gunakan API-SSL (port 8729, disarankan untuk produksi)
            </label>
          </div>

          <div class="form-group">
            <label class="form-label">Username API <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control"
              value="<?= clean($router['username'] ?? '') ?>" maxlength="100" required>
          </div>

          <div class="form-group">
            <label class="form-label">Password API</label>
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
          <li class="mb-2">Sinkronisasi berjalan <strong>satu arah</strong>: dari Mikrotik ke KahfiNet, lewat tombol "Sync dari Mikrotik" di menu Mikrotik &gt; Profile/Secret.</li>
          <li class="mb-2"><strong>Paket</strong> adalah data detail (harga, keterangan) yang menempel ke satu PPP Profile yang sudah disync. Bikin/edit profile baru langsung di Winbox dulu, lalu sync.</li>
          <li class="mb-2"><strong>Pelanggan</strong> adalah data detail (nama, alamat, dst) yang menempel ke satu PPP Secret yang sudah disync. Satu Profile/Secret hanya bisa dipakai 1 Paket/Pelanggan.</li>
          <li class="mb-2">Pelanggan yang menunggak akan di-isolir otomatis lewat cron harian (<code>cron/isolir_check.php</code>), yang men-disable PPP secret terkait di Mikrotik. Mengaktifkan kembali dilakukan manual lewat form Pelanggan.</li>
          <li>Kalau router sedang tidak bisa dihubungi, data cache lokal (Profile/Secret) tidak diubah — tetap menampilkan hasil sync terakhir.</li>
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
$('#btnTestConn').on('click', function() {
  var \$btn = $(this);
  var \$out = $('#testConnResult');
  \$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menghubungkan...');
  \$out.html('');

  $.post('{$base_url}modules/mikrotik/act.php?action=test_connection', $('#formRouter').serialize())
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
