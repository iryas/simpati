<?php
// ============================================================
//  KAHFINET - Modul Backup Database
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$backupTerakhir = app_setting('backup_terakhir', '');

$page_title  = 'Backup Database';
$active_menu = 'backup';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-database mr-2 text-primary"></i>Backup Database</h5>
</div>

<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <span><i class="fas fa-file-download mr-2 text-primary"></i>Backup Manual</span>
      </div>
      <div class="card-body">
        <p class="text-muted" style="font-size:14px">
          Tombol di bawah men-generate dump SQL lengkap (struktur + seluruh isi tabel)
          dari database <strong><?= clean(DB_NAME) ?></strong>, lalu langsung diunduh
          ke perangkatmu. Tidak ada salinan yang disimpan di server.
        </p>

        <div class="alert alert-warning py-2" style="font-size:13px">
          <i class="fas fa-exclamation-triangle mr-1"></i>
          File hasil backup berisi <strong>seluruh data pelanggan &amp; pembayaran</strong>.
          Simpan di tempat aman (jangan di folder yang gampang diakses orang lain),
          dan jangan dibagikan sembarangan.
        </div>

        <?php if ($backupTerakhir): ?>
          <p style="font-size:13px" class="mb-3">
            <i class="fas fa-clock text-muted mr-1"></i>
            Terakhir backup: <strong><?= tgl_indo($backupTerakhir, true) ?></strong>
          </p>
        <?php else: ?>
          <p style="font-size:13px" class="mb-3 text-muted">
            <i class="fas fa-info-circle mr-1"></i>
            Belum pernah backup dari halaman ini.
          </p>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>modules/backup/act.php" id="formBackup">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="download">
          <button type="submit" class="btn btn-primary" id="btnBackup">
            <i class="fas fa-download mr-1"></i>Backup &amp; Download Sekarang
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">
        <span><i class="fas fa-life-ring mr-2 text-primary"></i>Cara Restore</span>
      </div>
      <div class="card-body" style="font-size:13px">
        <p class="text-muted">File <code>.sql</code> hasil backup bisa dipulihkan lewat phpMyAdmin
          (menu Import) atau lewat terminal:</p>
        <pre class="bg-light p-2 rounded" style="font-size:12px;white-space:pre-wrap"><code>mysql -u [user] -p [nama_database] &lt; backup-....sql</code></pre>
        <p class="text-muted mb-0">Disarankan backup rutin secara berkala, terutama sebelum
          update aplikasi atau migrasi data besar.</p>
      </div>
    </div>
  </div>
</div>

<?php
$content  = ob_get_clean();
$extra_js = <<<'JS'
<script>
document.getElementById('formBackup').addEventListener('submit', function () {
  var btn = document.getElementById('btnBackup');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyiapkan backup...';
  setTimeout(function () {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-download mr-1"></i>Backup &amp; Download Sekarang';
  }, 4000);
});
</script>
JS;

require_once __DIR__ . '/../../template.php';
