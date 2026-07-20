<?php
// ============================================================
//  PORTAL PELANGGAN — Beranda / Dashboard
// ============================================================
require_once __DIR__ . '/_data.php';
portal_auth_check();

$me    = portal_current();
$pid   = (int)$me['id'];
$bulan = bulan_tagihan_sekarang();

$tagihan    = portal_tagihan_kini($pid);
$tunggakan  = portal_tunggakan($pid);
$usage      = portal_usage($pid, $bulan);
$pengumuman = portal_pengumuman(3);

// Nominal & status tagihan berjalan
$tg_nominal = $tagihan ? portal_nominal((int)$tagihan['jumlah'], (int)$tagihan['potongan']) : 0;
$tg_disc    = $tagihan ? ((int)$tagihan['jumlah'] - $tg_nominal) : 0;
$tg_lunas   = $tagihan && $tagihan['status'] === 'lunas';

$page_title = 'Beranda';
$active     = 'beranda';
$head_mode  = 'top';

ob_start();
?>

<!-- Kartu tagihan periode berjalan -->
<div class="bill-card">
  <div class="bc-label">
    <i class="fas fa-file-invoice-dollar mr-1"></i>
    Tagihan <?= $tg_lunas ? 'periode ini' : 'yang harus dibayar' ?>
  </div>

  <?php if ($tagihan): ?>
    <div class="bc-amount"><?= $tg_lunas ? rupiah((int)$tagihan['terbayar']) : rupiah($tg_nominal) ?></div>
    <div class="bc-period"><?= clean(portal_periode((string)$tagihan['bulan_tagihan'])) ?></div>
    <?php if ((int)$tagihan['potongan'] > 0): ?>
      <div style="font-size:12px;color:#4ade80;font-weight:700;margin-top:6px;">
        <i class="fas fa-tag" style="font-size:10px"></i>
        Termasuk potongan <?= (int)$tagihan['potongan'] ?> hari (&minus;<?= rupiah($tg_disc) ?>)
      </div>
    <?php endif; ?>
    <div class="bc-foot">
      <?php if ($tg_lunas): ?>
        <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Lunas</span>
        <span style="font-size:12px;color:rgba(255,255,255,.7)">
          Dibayar <?= tgl_indo((string)$tagihan['tgl_bayar']) ?>
        </span>
      <?php else: ?>
        <?= badge_status('belum', (string)$tagihan['bulan_tagihan']) ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="bc-amount" style="font-size:20px;">Belum ada tagihan</div>
    <div class="bc-period">Periode <?= clean(portal_periode($bulan)) ?></div>
  <?php endif; ?>
</div>

<!-- Peringatan tunggakan -->
<?php if ($tunggakan['count'] > 0): ?>
<a href="<?= PORTAL_URL ?>tagihan.php" class="pcard" style="display:block;border-color:#fecaca;background:#fff7f7;">
  <div class="irow" style="border:0;padding:2px;">
    <div class="ir-ic ic-red"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="ir-main">
      <div class="ir-t" style="color:#b91c1c;">Ada tunggakan <?= $tunggakan['count'] ?> bulan</div>
      <div class="ir-s">Total <?= rupiah($tunggakan['total']) ?> — ketuk untuk lihat</div>
    </div>
    <i class="fas fa-chevron-right text-muted2"></i>
  </div>
</a>
<?php endif; ?>

<!-- Info & Pengumuman terbaru -->
<?php if (!empty($pengumuman)): ?>
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;">
    <span>Info &amp; Pengumuman</span>
    <a href="<?= PORTAL_URL ?>info.php" style="font-size:12px;font-weight:600;color:#f59e0b;text-decoration:none;">Lihat semua ›</a>
  </div>
  <?php foreach (array_slice($pengumuman, 0, 2) as $p): ?>
    <?php [$warna, $ikon, $tlabel] = pengumuman_meta((string)$p['tipe']); ?>
    <a href="<?= PORTAL_URL ?>info.php" class="pcard" style="display:block;border-left:4px solid <?= $warna ?>;">
      <div class="irow" style="border:0;padding:2px;">
        <div class="ir-ic" style="background:<?= $warna ?>1a;color:<?= $warna ?>;"><i class="fas <?= $ikon ?>"></i></div>
        <div class="ir-main">
          <div class="ir-t">
            <?php if ((int)$p['pinned']): ?><i class="fas fa-thumbtack" style="font-size:10px;color:<?= $warna ?>;"></i> <?php endif; ?>
            <?= clean((string)$p['judul']) ?>
          </div>
          <div class="ir-s"><?= clean(mb_strimwidth((string)$p['isi'], 0, 62, '…')) ?></div>
        </div>
        <i class="fas fa-chevron-right text-muted2"></i>
      </div>
    </a>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Status langganan & paket -->
<div class="stat-row">
  <div class="stat">
    <div class="s-ic <?= $me['status']==='aktif' ? 'ic-green' : ($me['status']==='isolir' ? 'ic-amber' : 'ic-slate') ?>">
      <i class="fas fa-circle-notch"></i>
    </div>
    <div class="s-val" style="font-size:15px;text-transform:capitalize;"><?= clean($me['status']) ?></div>
    <div class="s-lbl">Status langganan</div>
  </div>
  <div class="stat">
    <div class="s-ic ic-blue"><i class="fas fa-tachometer-alt"></i></div>
    <div class="s-val" style="font-size:15px;"><?= $me['kecepatan_paket'] ? (int)$me['kecepatan_paket'].' Mbps' : '—' ?></div>
    <div class="s-lbl"><?= $me['nama_paket'] ? clean($me['nama_paket']) : 'Paket internet' ?></div>
  </div>
</div>

<!-- Pemakaian bulan ini -->
<div class="section-title">Pemakaian bulan ini</div>
<a href="<?= PORTAL_URL ?>usage.php" class="pcard" style="display:block;">
  <div class="irow" style="border:0;padding:2px;">
    <div class="ir-ic ic-amber"><i class="fas fa-chart-line"></i></div>
    <div class="ir-main">
      <div class="ir-t"><?= $usage ? format_bytes((int)$usage['bytes_out']) : '0 B' ?> terpakai</div>
      <div class="ir-s">
        <?php if ($usage && (int)$usage['uptime_seconds'] > 0): ?>
          Total online <?= format_uptime_seconds((int)$usage['uptime_seconds']) ?>
        <?php else: ?>
          Belum ada data pemakaian
        <?php endif; ?>
      </div>
    </div>
    <i class="fas fa-chevron-right text-muted2"></i>
  </div>
</a>

<!-- Menu cepat -->
<div class="section-title">Menu cepat</div>
<div class="pcard tight">
  <a href="<?= PORTAL_URL ?>riwayat.php" class="irow">
    <div class="ir-ic ic-green"><i class="fas fa-receipt"></i></div>
    <div class="ir-main"><div class="ir-t">Riwayat pembayaran</div><div class="ir-s">Lihat tagihan yang sudah lunas</div></div>
    <i class="fas fa-chevron-right text-muted2"></i>
  </a>
  <a href="<?= PORTAL_URL ?>lapor.php" class="irow">
    <div class="ir-ic ic-red"><i class="fas fa-headset"></i></div>
    <div class="ir-main"><div class="ir-t">Lapor gangguan</div><div class="ir-s">Internet bermasalah? Laporkan ke kami</div></div>
    <i class="fas fa-chevron-right text-muted2"></i>
  </a>
  <a href="<?= PORTAL_URL ?>profil.php" class="irow">
    <div class="ir-ic ic-slate"><i class="fas fa-user-cog"></i></div>
    <div class="ir-main"><div class="ir-t">Akun & PIN</div><div class="ir-s">Data langganan dan ubah PIN</div></div>
    <i class="fas fa-chevron-right text-muted2"></i>
  </a>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
