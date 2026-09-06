<?php
// ============================================================
//  KAHFINET - Modul Pengaturan Aplikasi
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN]);

$tgl_mulai               = app_setting('tgl_mulai_tagihan', '1');
$nama_isp                = app_setting('nama_isp', 'KahfiNet');
$no_cs                   = app_setting('no_cs', '');
$grace_period_isolir     = app_setting('grace_period_isolir', '3');
$mikrotik_profile_isolir = app_setting('mikrotik_profile_isolir', 'profile-Isolir2');
$fup_aktif               = app_setting('fup_aktif', '0');
$kuota_harian_gb         = app_setting('kuota_harian_gb', '7');
$mikrotik_profile_fup    = app_setting('mikrotik_profile_fup', 'profile-FUP');
$wablas_aktif        = app_setting('wablas_aktif', '0');
$wa_gateway          = app_setting('wa_gateway', 'wablas');
$wablas_token        = app_setting('wablas_token', '');
$wablas_secret       = app_setting('wablas_secret', '');
$fonnte_token        = app_setting('fonnte_token', '');
$telegram_aktif      = app_setting('telegram_aktif', '0');
$telegram_bot_token  = app_setting('telegram_bot_token', '');
$telegram_chat_id    = app_setting('telegram_chat_id', '');

// Tab aktif — dipertahankan lewat redirect abis simpan (act.php nambahin
// ?tab=... ke back_url), biar nggak balik ke tab pertama abis nyimpen.
$activeTab = in_array($_GET['tab'] ?? '', ['fup', 'wa', 'telegram'], true) ? $_GET['tab'] : 'umum';

$page_title  = 'Pengaturan Aplikasi';
$active_menu = 'pengaturan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-cog mr-2 text-primary"></i>Pengaturan Aplikasi</h5>
</div>

<div class="card">
  <div class="card-header" style="padding:0 20px;border-bottom:none">
    <ul class="nav nav-tabs" id="pengaturanTab" role="tablist">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'umum' ? 'active' : '' ?>" id="tab-umum" data-toggle="tab"
           href="#pane-umum" role="tab" aria-controls="pane-umum" aria-selected="<?= $activeTab === 'umum' ? 'true' : 'false' ?>">
          <i class="fas fa-sliders-h mr-1"></i>Umum
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'fup' ? 'active' : '' ?>" id="tab-fup" data-toggle="tab"
           href="#pane-fup" role="tab" aria-controls="pane-fup" aria-selected="<?= $activeTab === 'fup' ? 'true' : 'false' ?>">
          <i class="fas fa-tachometer-alt mr-1"></i>FUP
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'wa' ? 'active' : '' ?>" id="tab-wa" data-toggle="tab"
           href="#pane-wa" role="tab" aria-controls="pane-wa" aria-selected="<?= $activeTab === 'wa' ? 'true' : 'false' ?>">
          <i class="fab fa-whatsapp mr-1 text-success"></i>WhatsApp Gateway
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'telegram' ? 'active' : '' ?>" id="tab-telegram" data-toggle="tab"
           href="#pane-telegram" role="tab" aria-controls="pane-telegram" aria-selected="<?= $activeTab === 'telegram' ? 'true' : 'false' ?>">
          <i class="fab fa-telegram mr-1" style="color:#229ED9"></i>Notifikasi Telegram
        </a>
      </li>
    </ul>
  </div>

  <div class="card-body">
    <div class="tab-content" id="pengaturanTabContent">

      <!-- ============ TAB: UMUM ============ -->
      <div class="tab-pane fade <?= $activeTab === 'umum' ? 'show active' : '' ?>" id="pane-umum" role="tabpanel" aria-labelledby="tab-umum">
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

          <div class="form-group">
            <label class="form-label font-weight-bold">Nama Profile Isolir (Mikrotik)</label>
            <input type="text" name="mikrotik_profile_isolir" class="form-control"
                   value="<?= clean($mikrotik_profile_isolir) ?>" maxlength="100"
                   placeholder="Contoh: profile-Isolir2">
            <small class="text-muted">Nama PPP Profile di Mikrotik yang dipakai saat pelanggan diisolir.</small>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Pengaturan
          </button>
        </form>
      </div>

      <!-- ============ TAB: FUP ============ -->
      <div class="tab-pane fade <?= $activeTab === 'fup' ? 'show active' : '' ?>" id="pane-fup" role="tabpanel" aria-labelledby="tab-fup">
        <form method="POST" action="<?= BASE_URL ?>modules/pengaturan/act.php">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save_fup">

          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="fup_aktif"
                     name="fup_aktif" value="1" <?= $fup_aktif === '1' ? 'checked' : '' ?>>
              <label class="custom-control-label font-weight-bold" for="fup_aktif">
                Aktifkan FUP (Fair Usage Policy)
              </label>
            </div>
            <small class="text-muted">
              <?= $fup_aktif === '1' ? 'FUP <strong>aktif</strong> — pelanggan lewat kuota otomatis di-throttle.' : 'FUP saat ini <strong>nonaktif</strong> — nggak ada pelanggan yang di-throttle otomatis walau lewat kuota.' ?>
            </small>
          </div>

          <div class="form-group">
            <label class="form-label font-weight-bold">Kuota Harian FUP</label>
            <div class="d-flex align-items-center" style="gap:10px">
              <input type="number" name="kuota_harian_gb" class="form-control"
                     value="<?= clean($kuota_harian_gb) ?>" min="0" max="1000" step="0.1" required
                     style="width:110px">
              <span class="text-muted" style="font-size:14px;white-space:nowrap">GB / hari / pelanggan</span>
            </div>
            <small class="text-muted mt-1 d-block">
              Pelanggan yang pemakaian hariannya lewat angka ini otomatis di-throttle ke profile FUP
              sampai tengah malam (reset otomatis, balik ke profile paket masing-masing).
              Cuma berlaku kalau toggle di atas aktif.
            </small>
          </div>

          <div class="form-group">
            <label class="form-label font-weight-bold">Nama Profile FUP (Mikrotik)</label>
            <input type="text" name="mikrotik_profile_fup" class="form-control"
                   value="<?= clean($mikrotik_profile_fup) ?>" maxlength="100"
                   placeholder="Contoh: profile-FUP">
            <small class="text-muted">Nama PPP Profile di Mikrotik yang dipakai saat pelanggan kena FUP (harus sudah dibuat manual di router).</small>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan Pengaturan
          </button>
        </form>
      </div>

      <!-- ============ TAB: WHATSAPP GATEWAY ============ -->
      <div class="tab-pane fade <?= $activeTab === 'wa' ? 'show active' : '' ?>" id="pane-wa" role="tabpanel" aria-labelledby="tab-wa">
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

      <!-- ============ TAB: NOTIFIKASI TELEGRAM ============ -->
      <div class="tab-pane fade <?= $activeTab === 'telegram' ? 'show active' : '' ?>" id="pane-telegram" role="tabpanel" aria-labelledby="tab-telegram">
        <form method="POST" action="<?= BASE_URL ?>modules/pengaturan/act.php" id="formTelegram">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save_telegram">

          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="telegram_aktif"
                     name="telegram_aktif" value="1" <?= $telegram_aktif === '1' ? 'checked' : '' ?>>
              <label class="custom-control-label font-weight-bold" for="telegram_aktif">
                Aktifkan notifikasi Telegram
              </label>
            </div>
            <small class="text-muted">
              Kirim alert otomatis ke Telegram saat ONU offline (cluster/&gt;30 menit) atau sinyal
              kritis/waspada. Dicek tiap kali <code>acs:sync</code> jalan.
            </small>
          </div>

          <div class="form-group mb-2">
            <label class="form-label font-weight-bold">Bot Token</label>
            <div class="input-group">
              <input type="password" name="telegram_bot_token" id="inputTelegramToken" class="form-control"
                     value="<?= clean($telegram_bot_token) ?>" maxlength="200"
                     placeholder="Dari @BotFather, mis. 123456:AAExxxxx">
              <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary btn-sm btn-toggle-secret"
                        data-target="inputTelegramToken">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            <small class="text-muted">Buat bot via @BotFather → <code>/newbot</code>, salin API Token-nya.</small>
          </div>

          <div class="form-group mb-2">
            <label class="form-label font-weight-bold">Chat ID</label>
            <input type="text" name="telegram_chat_id" id="inputTelegramChatId" class="form-control"
                   value="<?= clean($telegram_chat_id) ?>" maxlength="60"
                   placeholder="Mis. -1001234567890 (grup) atau 123456789 (pribadi)">
            <small class="text-muted">
              Kirim pesan ke bot/grup, lalu buka
              <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> untuk lihat <code>chat.id</code>.
            </small>
          </div>

          <?php if ($telegram_bot_token && $telegram_chat_id): ?>
          <div class="alert alert-success py-2 mb-3" style="font-size:13px">
            <i class="fas fa-check-circle mr-1"></i>
            Token &amp; Chat ID sudah tersimpan.
            <?= $telegram_aktif === '1' ? 'Notifikasi <strong>aktif</strong>.' : 'Notifikasi saat ini <strong>nonaktif</strong>.' ?>
          </div>
          <?php else: ?>
          <div class="alert alert-warning py-2 mb-3" style="font-size:13px">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Token dan Chat ID belum diisi lengkap.
          </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i>Simpan
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestTelegram">
            <i class="fas fa-paper-plane mr-1"></i>Tes Kirim
          </button>
          <span id="testTelegramResult" class="ml-2" style="font-size:12.5px"></span>
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

// Tes kirim notifikasi Telegram (pakai nilai di form saat ini, walau belum disimpan)
$('#btnTestTelegram').on('click', function () {
  const btn    = $(this);
  const result = $('#testTelegramResult');
  const token  = $('#inputTelegramToken').val().trim();
  const chatId = $('#inputTelegramChatId').val().trim();

  if (!token || !chatId) {
    result.html('<span class="text-danger"><i class="fas fa-times-circle mr-1"></i>Isi Token &amp; Chat ID dulu.</span>');
    return;
  }

  btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Mengirim...');
  result.html('');

  $.post('act.php', {
    action: 'test_telegram',
    csrf_token: $('input[name="csrf_token"]').first().val(),
    telegram_bot_token: token,
    telegram_chat_id: chatId,
  }).done(function (res) {
    if (res.success) {
      result.html('<span class="text-success"><i class="fas fa-check-circle mr-1"></i>' + res.msg + '</span>');
    } else {
      result.html('<span class="text-danger"><i class="fas fa-times-circle mr-1"></i>' + res.msg + '</span>');
    }
  }).fail(function () {
    result.html('<span class="text-danger"><i class="fas fa-times-circle mr-1"></i>Gagal menghubungi server.</span>');
  }).always(function () {
    btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i>Tes Kirim');
  });
});
</script>
JS;
require_once __DIR__ . '/../../template.php';
