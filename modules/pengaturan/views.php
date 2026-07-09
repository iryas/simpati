<?php
// ============================================================
//  KAHFINET - Modul Pengaturan Aplikasi
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$tgl_mulai           = app_setting('tgl_mulai_tagihan', '1');
$nama_isp            = app_setting('nama_isp', 'KahfiNet');
$no_cs               = app_setting('no_cs', '');
$grace_period_isolir = app_setting('grace_period_isolir', '3');
$wablas_aktif        = app_setting('wablas_aktif', '0');
$wa_gateway          = app_setting('wa_gateway', 'wablas');
$wablas_token        = app_setting('wablas_token', '');
$wablas_secret       = app_setting('wablas_secret', '');
$fonnte_token        = app_setting('fonnte_token', '');

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

          <div class="form-group">
            <label class="form-label font-weight-bold">No. CS / WhatsApp Admin</label>
            <input type="text" name="no_cs" class="form-control"
                   value="<?= clean($no_cs) ?>" maxlength="20"
                   placeholder="Contoh: 081234567890">
            <small class="text-muted">Dipakai sebagai kontak konfirmasi pembayaran di pesan WA isolir (placeholder <code>{no_cs}</code>).</small>
          </div>

          <div class="form-group">
            <label class="form-label font-weight-bold">Grace Period Isolir</label>
            <div class="d-flex align-items-center" style="gap:10px">
              <input type="number" name="grace_period_isolir" class="form-control"
                     value="<?= (int)$grace_period_isolir ?>" min="0" max="30" required
                     style="width:90px">
              <span class="text-muted" style="font-size:14px;white-space:nowrap">hari setelah tanggal mulai tagihan</span>
            </div>
            <small class="text-muted mt-1 d-block">
              Pelanggan belum bayar lewat tgl
              <strong><?= (int)$tgl_mulai + (int)$grace_period_isolir ?></strong>
              akan muncul di widget isolir dashboard.
              Contoh: mulai tgl <strong><?= (int)$tgl_mulai ?></strong> + grace
              <strong><?= (int)$grace_period_isolir ?></strong> hari =
              isolir mulai tgl <strong><?= (int)$tgl_mulai + (int)$grace_period_isolir ?></strong>.
            </small>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Pengaturan
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- WhatsApp Gateway -->
<div class="row mt-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <span><i class="fab fa-whatsapp mr-2 text-success"></i>WhatsApp Gateway</span>
      </div>
      <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>modules/pengaturan/act.php">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save_wa">

          <!-- Toggle aktif WA -->
          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="wablas_aktif"
                     name="wablas_aktif" value="1" <?= $wablas_aktif === '1' ? 'checked' : '' ?>>
              <label class="custom-control-label font-weight-bold" for="wablas_aktif">
                Aktifkan pengiriman pesan WhatsApp otomatis
              </label>
            </div>
            <small class="text-muted">Pesan dikirim saat pembayaran dikonfirmasi dan saat pelanggan diisolir.</small>
          </div>

          <!-- Pilih Gateway -->
          <div class="form-group">
            <label class="form-label font-weight-bold">Gateway</label>
            <div class="d-flex" style="gap:20px">
              <div class="custom-control custom-radio">
                <input type="radio" id="gw_fonnte" name="wa_gateway" value="fonnte"
                       class="custom-control-input" <?= $wa_gateway === 'fonnte' ? 'checked' : '' ?>>
                <label class="custom-control-label font-weight-bold text-success" for="gw_fonnte">
                  Fonnte <span class="badge badge-success ml-1" style="font-size:10px">Rekomendasi</span>
                </label>
              </div>
              <div class="custom-control custom-radio">
                <input type="radio" id="gw_wablas" name="wa_gateway" value="wablas"
                       class="custom-control-input" <?= $wa_gateway === 'wablas' ? 'checked' : '' ?>>
                <label class="custom-control-label" for="gw_wablas">Wablas</label>
              </div>
            </div>
          </div>

          <!-- Seksi Fonnte -->
          <div id="seksi_fonnte" <?= $wa_gateway !== 'fonnte' ? 'style="display:none"' : '' ?>>
            <div class="card bg-light border-0 mb-3">
              <div class="card-body py-3">
                <div class="form-group mb-2">
                  <label class="form-label font-weight-bold">Token Fonnte</label>
                  <div class="input-group">
                    <input type="password" name="fonnte_token" id="inputFonnteToken" class="form-control"
                           value="<?= clean($fonnte_token) ?>" maxlength="500"
                           placeholder="Token dari dashboard Fonnte">
                    <div class="input-group-append">
                      <button type="button" class="btn btn-outline-secondary btn-sm btn-toggle-secret"
                              data-target="inputFonnteToken">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                  </div>
                  <small class="text-muted">Didapat dari dashboard Fonnte → Device → Token. Format header: <code>Authorization: TOKEN</code></small>
                </div>
                <?php if ($fonnte_token): ?>
                <div class="alert alert-success py-2 mb-0" style="font-size:13px">
                  <i class="fas fa-check-circle mr-1"></i>
                  Token Fonnte sudah tersimpan.
                  <?= $wablas_aktif === '1' ? 'Gateway <strong>aktif</strong>.' : 'Gateway saat ini <strong>nonaktif</strong>.' ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning py-2 mb-0" style="font-size:13px">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  Token belum diisi. Daftar di <strong>fonnte.com</strong>.
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Seksi Wablas -->
          <div id="seksi_wablas" <?= $wa_gateway !== 'wablas' ? 'style="display:none"' : '' ?>>
            <div class="card bg-light border-0 mb-3">
              <div class="card-body py-3">
                <div class="form-group mb-2">
                  <label class="form-label font-weight-bold">Token Wablas</label>
                  <input type="text" name="wablas_token" class="form-control"
                         value="<?= clean($wablas_token) ?>" maxlength="500"
                         placeholder="Token dari Device Settings Wablas">
                  <small class="text-muted">Dari menu <strong>Device → Settings</strong> di dashboard Wablas.</small>
                </div>
                <div class="form-group mb-2">
                  <label class="form-label font-weight-bold">Secret Key Wablas</label>
                  <div class="input-group">
                    <input type="password" name="wablas_secret" id="inputWablasSecret" class="form-control"
                           value="<?= clean($wablas_secret) ?>" maxlength="500"
                           placeholder="Secret key dari Wablas">
                    <div class="input-group-append">
                      <button type="button" class="btn btn-outline-secondary btn-sm btn-toggle-secret"
                              data-target="inputWablasSecret">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                  </div>
                  <small class="text-muted">Format header: <code>Authorization: {token}.{secret_key}</code></small>
                </div>
                <?php if ($wablas_token && $wablas_secret): ?>
                <div class="alert alert-success py-2 mb-0" style="font-size:13px">
                  <i class="fas fa-check-circle mr-1"></i>
                  Token &amp; secret sudah tersimpan.
                  <?= $wablas_aktif === '1' ? 'Gateway <strong>aktif</strong>.' : 'Gateway saat ini <strong>nonaktif</strong>.' ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning py-2 mb-0" style="font-size:13px">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  Token dan secret belum diisi. Daftar di <strong>deu.wablas.com</strong>.
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Pengaturan WA
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$content  = ob_get_clean();
$extra_js = <<<'JS'
<script>
// Toggle show/hide password
$(document).on('click', '.btn-toggle-secret', function () {
  const inp  = $('#' + $(this).data('target'));
  const icon = $(this).find('i');
  if (inp.attr('type') === 'password') {
    inp.attr('type', 'text');
    icon.removeClass('fa-eye').addClass('fa-eye-slash');
  } else {
    inp.attr('type', 'password');
    icon.removeClass('fa-eye-slash').addClass('fa-eye');
  }
});

// Toggle seksi gateway
$('input[name="wa_gateway"]').on('change', function () {
  if ($(this).val() === 'fonnte') {
    $('#seksi_fonnte').show();
    $('#seksi_wablas').hide();
  } else {
    $('#seksi_fonnte').hide();
    $('#seksi_wablas').show();
  }
});
</script>
JS;
require_once __DIR__ . '/../../template.php';
