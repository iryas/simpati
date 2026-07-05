<?php
// ============================================================
//  KAHFINET - Modul Pengaturan Aplikasi
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$tgl_mulai     = app_setting('tgl_mulai_tagihan', '1');
$nama_isp      = app_setting('nama_isp', 'KahfiNet');
$wablas_aktif  = app_setting('wablas_aktif', '0');
$wablas_token  = app_setting('wablas_token', '');
$wablas_secret = app_setting('wablas_secret', '');

$page_title  = 'Pengaturan Aplikasi';
$active_menu = 'pengaturan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-cog mr-2 text-primary"></i>Pengaturan Aplikasi</h5>
</div>

<div class="row">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <span><i class="fas fa-sliders-h mr-2 text-primary"></i>Pengaturan Umum</span>
      </div>
      <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>modules/pengaturan/act.php">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save">

          <div class="form-group">
            <label class="form-label font-weight-bold">Nama ISP</label>
            <input type="text" name="nama_isp" class="form-control"
                   value="<?= clean($nama_isp) ?>" maxlength="100" required>
            <small class="text-muted">Nama perusahaan/ISP yang muncul di aplikasi.</small>
          </div>

          <div class="form-group">
            <label class="form-label font-weight-bold">Tanggal Mulai Tagihan</label>
            <div class="d-flex align-items-center" style="gap:10px">
              <input type="number" name="tgl_mulai_tagihan" class="form-control"
                     value="<?= (int)$tgl_mulai ?>" min="1" max="28" required
                     style="width:90px">
              <span class="text-muted" style="font-size:14px;white-space:nowrap">setiap bulan (maks. tgl 28)</span>
            </div>
            <small class="text-muted mt-1 d-block">
              Generate tagihan hanya bisa dilakukan mulai tanggal ini.
              Contoh: diset <strong><?= (int)$tgl_mulai ?></strong> → tagihan Juli digenerate mulai
              <strong><?= (int)$tgl_mulai ?> Juli</strong>,
              berlaku hingga <strong><?= (int)$tgl_mulai - 1 ?> Agustus</strong>.
            </small>
          </div>

          <?php
            $tgl        = (int)$tgl_mulai;
            $now        = (int)date('j');
            $bln_indo   = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            $bln_label  = $bln_indo[(int)date('n')] . ' ' . date('Y');
            $lbl_periode = label_periode_tagihan(date('Y-m'), $tgl);
            $boleh      = $now >= $tgl;
          ?>
          <div class="alert <?= $boleh ? 'alert-success' : 'alert-warning' ?> py-2" style="font-size:13px">
            <i class="fas <?= $boleh ? 'fa-check-circle' : 'fa-clock' ?> mr-1"></i>
            <?php if ($boleh): ?>
              Generate tagihan bulan <strong><?= $bln_label ?></strong>
              sudah <strong>boleh</strong> dilakukan.
              Periode layanan: <strong><?= $lbl_periode ?></strong>.
            <?php else: ?>
              Generate tagihan bulan <strong><?= $bln_label ?></strong>
              baru boleh mulai <strong>tgl <?= $tgl ?></strong>.
              Hari ini masih tgl <?= $now ?>.
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Pengaturan
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Wablas WhatsApp Gateway -->
<div class="row mt-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <span><i class="fab fa-whatsapp mr-2 text-success"></i>WhatsApp Gateway (Wablas)</span>
      </div>
      <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>modules/pengaturan/act.php">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save_wablas">

          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="wablas_aktif"
                     name="wablas_aktif" value="1" <?= $wablas_aktif === '1' ? 'checked' : '' ?>>
              <label class="custom-control-label font-weight-bold" for="wablas_aktif">
                Aktifkan kirim bukti pembayaran via WhatsApp
              </label>
            </div>
            <small class="text-muted">Pesan otomatis dikirim ke pelanggan setiap pembayaran dikonfirmasi.</small>
          </div>

          <div class="form-group">
            <label class="form-label font-weight-bold">Token Wablas</label>
            <input type="text" name="wablas_token" class="form-control"
                   value="<?= clean($wablas_token) ?>" maxlength="500"
                   placeholder="Token dari Device Settings Wablas">
            <small class="text-muted">Didapat dari menu <strong>Device &rarr; Settings</strong> di dashboard Wablas.</small>
          </div>

          <div class="form-group">
            <label class="form-label font-weight-bold">Secret Key Wablas</label>
            <div class="input-group">
              <input type="password" name="wablas_secret" id="inputWablasSecret" class="form-control"
                     value="<?= clean($wablas_secret) ?>" maxlength="500"
                     placeholder="Secret key dari Wablas">
              <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleWablasSecret">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            <small class="text-muted">Format header: <code>Authorization: {token}.{secret_key}</code></small>
          </div>

          <?php if ($wablas_token && $wablas_secret): ?>
          <div class="alert alert-success py-2" style="font-size:13px">
            <i class="fas fa-check-circle mr-1"></i>
            Token &amp; secret sudah tersimpan.
            <?= $wablas_aktif === '1' ? 'Gateway <strong>aktif</strong>.' : 'Gateway saat ini <strong>nonaktif</strong>.' ?>
          </div>
          <?php else: ?>
          <div class="alert alert-warning py-2" style="font-size:13px">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Token dan secret belum diisi. Daftar di <strong>deu.wablas.com</strong>.
          </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Pengaturan WA
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$('#toggleWablasSecret').on('click', function () {
  const inp  = $('#inputWablasSecret');
  const icon = $(this).find('i');
  if (inp.attr('type') === 'password') {
    inp.attr('type', 'text');
    icon.removeClass('fa-eye').addClass('fa-eye-slash');
  } else {
    inp.attr('type', 'password');
    icon.removeClass('fa-eye-slash').addClass('fa-eye');
  }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../template.php';
