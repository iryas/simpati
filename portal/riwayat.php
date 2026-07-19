<?php
// ============================================================
//  PORTAL PELANGGAN — Riwayat Pembayaran
// ============================================================
require_once __DIR__ . '/_data.php';
portal_auth_check();

$me      = portal_current();
$pid     = (int)$me['id'];
$riwayat = portal_riwayat($pid, 60);

// Kelompokkan per tahun
$grouped = [];
foreach ($riwayat as $r) {
    $th = $r['tgl_bayar'] ? date('Y', strtotime((string)$r['tgl_bayar'])) : '—';
    $grouped[$th][] = $r;
}

$page_title = 'Riwayat Pembayaran';
$page_sub   = count($riwayat) . ' pembayaran lunas';
$active     = 'tagihan';

ob_start();
?>

<?php if (empty($riwayat)): ?>
  <div class="pcard">
    <div class="empty">
      <i class="fas fa-receipt"></i>
      <p>Belum ada riwayat pembayaran.</p>
    </div>
  </div>
<?php else: ?>

  <?php foreach ($grouped as $tahun => $rows): ?>
    <div class="section-title">Tahun <?= clean((string)$tahun) ?></div>
    <div class="pcard tight">
      <?php foreach ($rows as $r): ?>
        <a href="<?= PORTAL_URL ?>struk.php?id=<?= (int)$r['id'] ?>" class="irow">
          <div class="ir-ic ic-green"><i class="fas fa-check"></i></div>
          <div class="ir-main">
            <div class="ir-t"><?= clean(portal_periode((string)$r['bulan_tagihan'])) ?></div>
            <div class="ir-s">
              <i class="far fa-calendar-alt"></i> <?= $r['tgl_bayar'] ? tgl_indo((string)$r['tgl_bayar']) : '—' ?>
              &middot; <?= $r['metode'] === 'transfer' ? 'Transfer' : 'Tunai' ?>
            </div>
          </div>
          <div class="ir-right">
            <div class="ir-amt"><?= rupiah((int)$r['terbayar']) ?></div>
            <div class="ir-s" style="color:#16a34a;">Lunas</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
