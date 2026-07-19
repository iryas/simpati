<?php
// ============================================================
//  PORTAL PELANGGAN — Akun & PIN
// ============================================================
require_once __DIR__ . '/_data.php';
portal_auth_check();

$me       = portal_current();
$nama_isp = clean(app_setting('nama_isp', 'KahfiNet'));

$page_title = 'Akun Saya';
$page_sub   = 'Data langganan & keamanan';
$active     = 'akun';

ob_start();
?>

<!-- Identitas -->
<div class="pcard" style="text-align:center;padding-top:22px;">
  <div style="width:66px;height:66px;border-radius:20px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;
              background:linear-gradient(135deg,var(--amber),var(--amber-2));color:#1a1a1a;font-size:28px;">
    <i class="fas fa-user"></i>
  </div>
  <div style="font-size:18px;font-weight:800;color:var(--navy);"><?= clean($me['nama']) ?></div>
  <div style="font-size:13px;color:var(--muted);margin-top:2px;">
    <i class="fas fa-mobile-alt"></i> <?= clean((string)($me['no_hp'] ?? '—')) ?>
  </div>
  <div style="margin-top:10px;"><?= badge_status((string)$me['status']) ?></div>
</div>

<!-- Detail langganan -->
<div class="section-title">Detail langganan</div>
<div class="pcard">
  <div class="kv"><span class="k">Paket</span><span class="v"><?= $me['nama_paket'] ? clean($me['nama_paket']) : '—' ?></span></div>
  <div class="kv"><span class="k">Kecepatan</span><span class="v"><?= $me['kecepatan_paket'] ? (int)$me['kecepatan_paket'].' Mbps' : '—' ?></span></div>
  <div class="kv"><span class="k">Area</span><span class="v"><?= $me['nama_area'] ? clean($me['nama_area']) : '—' ?></span></div>
  <div class="kv"><span class="k">Alamat</span><span class="v" style="max-width:60%;"><?= $me['alamat'] ? clean((string)$me['alamat']) : '—' ?></span></div>
  <div class="kv"><span class="k">Terdaftar sejak</span><span class="v"><?= $me['tgl_daftar'] ? tgl_indo((string)$me['tgl_daftar']) : '—' ?></span></div>
</div>

<!-- Ganti PIN -->
<div class="section-title">Ubah PIN</div>
<div class="pcard">
  <form method="POST" action="<?= PORTAL_URL ?>act.php?action=ganti_pin" autocomplete="off">
    <?php csrf_field(); ?>
    <label class="plabel">PIN lama</label>
    <input type="password" name="pin_lama" class="pinput pin-in" inputmode="numeric" maxlength="6" placeholder="••••••" required>

    <label class="plabel">PIN baru (6 digit)</label>
    <input type="password" name="pin_baru" class="pinput pin-in" inputmode="numeric" maxlength="6" placeholder="••••••" required>

    <label class="plabel">Ulangi PIN baru</label>
    <input type="password" name="pin_ulang" class="pinput pin-in" inputmode="numeric" maxlength="6" placeholder="••••••" required>

    <button type="submit" class="pbtn pbtn-navy"><i class="fas fa-key"></i> Simpan PIN Baru</button>
  </form>
</div>

<!-- Logout -->
<div class="pcard" style="border:0;box-shadow:none;background:transparent;padding:0;margin-top:4px;">
  <a href="<?= PORTAL_URL ?>act.php?action=logout" class="pbtn pbtn-ghost"
     onclick="return confirm('Keluar dari portal?')" style="color:#dc2626;border-color:#fecaca;">
    <i class="fas fa-sign-out-alt"></i> Keluar
  </a>
</div>

<div style="text-align:center;font-size:11.5px;color:var(--muted);margin:16px 0 8px;">
  <?= $nama_isp ?> · Portal Pelanggan
</div>

<?php
$content = ob_get_clean();
$body_extra = <<<HTML
<script>
  document.querySelectorAll('.pin-in').forEach(function (el) {
    el.addEventListener('input', function () { this.value = this.value.replace(/\\D/g, ''); });
  });
</script>
HTML;
require __DIR__ . '/layout.php';
