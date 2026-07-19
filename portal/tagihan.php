<?php
// ============================================================
//  PORTAL PELANGGAN — Tagihan & Status
// ============================================================
require_once __DIR__ . '/_data.php';
portal_auth_check();

$me    = portal_current();
$pid   = (int)$me['id'];
$bulan = bulan_tagihan_sekarang();

$tagihan   = portal_tagihan_kini($pid);
$tunggakan = portal_tunggakan($pid);

$tg_nominal = $tagihan ? portal_nominal((int)$tagihan['jumlah'], (int)$tagihan['potongan']) : 0;
$tg_belum   = $tagihan && $tagihan['status'] === 'belum';

$total_bayar = ($tg_belum ? $tg_nominal : 0) + $tunggakan['total'];

$no_cs = preg_replace('/[^0-9]/', '', app_setting('no_cs', ''));
$wa_cs = $no_cs ? 'https://wa.me/' . format_no_hp_wa($no_cs)
         . '?text=' . rawurlencode('Halo, saya ' . $me['nama'] . ' ingin membayar tagihan internet.') : '';

$page_title = 'Tagihan Saya';
$page_sub   = 'Status & rincian tagihan';
$active     = 'tagihan';

ob_start();
?>

<!-- Total yang harus dibayar -->
<div class="bill-card">
  <div class="bc-label"><i class="fas fa-wallet mr-1"></i> Total harus dibayar</div>
  <div class="bc-amount"><?= rupiah($total_bayar) ?></div>
  <div class="bc-period">
    <?php if ($total_bayar > 0): ?>
      <?= $tunggakan['count'] > 0 ? 'Termasuk ' . $tunggakan['count'] . ' bulan tunggakan' : 'Tagihan periode berjalan' ?>
    <?php else: ?>
      Tidak ada tagihan tertunggak 🎉
    <?php endif; ?>
  </div>
  <?php if ($total_bayar > 0 && $wa_cs): ?>
  <div class="bc-foot" style="justify-content:flex-start;">
    <a href="<?= $wa_cs ?>" target="_blank" rel="noopener"
       class="pbtn pbtn-amber pbtn-sm" style="width:auto;">
      <i class="fab fa-whatsapp"></i> Konfirmasi bayar ke kasir
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Tagihan periode berjalan -->
<div class="section-title">Periode berjalan</div>
<div class="pcard">
  <?php if ($tagihan): ?>
    <div class="kv"><span class="k">Periode</span><span class="v"><?= clean(portal_periode((string)$tagihan['bulan_tagihan'])) ?></span></div>
    <div class="kv"><span class="k">Paket</span><span class="v"><?= $me['nama_paket'] ? clean($me['nama_paket']) : '—' ?></span></div>
    <div class="kv"><span class="k">Tagihan</span><span class="v"><?= rupiah((int)$tagihan['jumlah']) ?></span></div>
    <?php if ((int)$tagihan['potongan'] > 0): ?>
      <div class="kv"><span class="k">Potongan</span><span class="v" style="color:#16a34a;"><?= (int)$tagihan['potongan'] ?> hari</span></div>
    <?php endif; ?>
    <div class="kv">
      <span class="k">Status</span>
      <span class="v">
        <?php if ($tagihan['status'] === 'lunas'): ?>
          <?= badge_status('lunas') ?>
        <?php else: ?>
          <?= badge_status('belum', (string)$tagihan['bulan_tagihan']) ?>
        <?php endif; ?>
      </span>
    </div>
    <div class="kv">
      <span class="k"><?= $tagihan['status'] === 'lunas' ? 'Dibayar' : 'Yang harus dibayar' ?></span>
      <span class="v" style="font-size:16px;color:var(--navy);">
        <?= $tagihan['status'] === 'lunas' ? rupiah((int)$tagihan['terbayar']) : rupiah($tg_nominal) ?>
      </span>
    </div>
  <?php else: ?>
    <div class="empty" style="padding:24px 10px;">
      <i class="far fa-calendar-check"></i>
      <p>Belum ada tagihan untuk periode<br><?= clean(portal_periode($bulan)) ?>.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Tunggakan -->
<?php if ($tunggakan['count'] > 0): ?>
<div class="section-title">Tunggakan (<?= $tunggakan['count'] ?> bulan)</div>
<div class="pcard tight">
  <?php foreach ($tunggakan['items'] as $t): ?>
    <div class="irow">
      <div class="ir-ic ic-red"><i class="fas fa-calendar-times"></i></div>
      <div class="ir-main">
        <div class="ir-t"><?= clean(portal_periode((string)$t['bulan_tagihan'])) ?></div>
        <div class="ir-s">
          <?= badge_status('belum', (string)$t['bulan_tagihan']) ?>
          <?php if ((int)$t['potongan'] > 0): ?><span class="ir-s"> · potongan <?= (int)$t['potongan'] ?> hari</span><?php endif; ?>
        </div>
      </div>
      <div class="ir-right"><div class="ir-amt"><?= rupiah((int)$t['nominal']) ?></div></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="pcard" style="background:#f8fafc;border-style:dashed;">
  <div class="irow" style="border:0;padding:2px;">
    <div class="ir-ic ic-blue"><i class="fas fa-info-circle"></i></div>
    <div class="ir-main">
      <div class="ir-t" style="font-size:13px;">Pembayaran</div>
      <div class="ir-s">Pembayaran dilakukan lewat kasir/admin<?= $no_cs ? '. Konfirmasi via tombol WhatsApp di atas.' : '.' ?></div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
