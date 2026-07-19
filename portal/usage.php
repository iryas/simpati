<?php
// ============================================================
//  PORTAL PELANGGAN — Pemakaian Bandwidth
// ============================================================
require_once __DIR__ . '/_data.php';
portal_auth_check();

$me    = portal_current();
$pid   = (int)$me['id'];
$bulan = bulan_tagihan_sekarang();

$kini    = portal_usage($pid, $bulan);
$riwayat = portal_usage_riwayat($pid, 6);   // desc (terbaru dulu)

// Skala maksimum untuk bar
$maks = 1;
foreach ($riwayat as $r) $maks = max($maks, (int)$r['bytes_out']);

$page_title = 'Pemakaian';
$page_sub   = 'Data internet terpakai';
$active     = 'usage';

ob_start();
?>

<!-- Ringkasan bulan ini -->
<div class="bill-card">
  <div class="bc-label"><i class="fas fa-chart-line mr-1"></i> Pemakaian periode ini</div>
  <div class="bc-amount"><?= $kini ? format_bytes((int)$kini['bytes_out']) : '0 B' ?></div>
  <div class="bc-period"><?= clean(portal_periode($bulan)) ?></div>
  <div class="bc-foot">
    <span style="font-size:12px;color:rgba(255,255,255,.75)">
      <i class="fas fa-power-off mr-1"></i>
      Online <?= $kini && (int)$kini['uptime_seconds'] > 0 ? format_uptime_seconds((int)$kini['uptime_seconds']) : '—' ?>
    </span>
    <span style="font-size:11px;color:rgba(255,255,255,.55)">
      <?php if ($kini && $kini['last_poll_at']): ?>
        Update <?= tgl_indo((string)$kini['last_poll_at'], true) ?>
      <?php endif; ?>
    </span>
  </div>
</div>

<!-- Riwayat pemakaian -->
<div class="section-title">Riwayat 6 bulan terakhir</div>
<div class="pcard">
  <?php if (empty($riwayat)): ?>
    <div class="empty" style="padding:24px 10px;">
      <i class="fas fa-chart-bar"></i>
      <p>Belum ada data pemakaian.<br>Data mulai tercatat setelah router aktif memantau.</p>
    </div>
  <?php else: ?>
    <?php foreach ($riwayat as $r):
      $b = (int)$r['bytes_out'];
      $pct = $maks > 0 ? max(3, round($b / $maks * 100)) : 3;
      $isNow = $r['bulan_tagihan'] === $bulan;
    ?>
      <div style="margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">
          <span style="font-size:13px;font-weight:600;color:var(--ink);">
            <?= clean(portal_periode((string)$r['bulan_tagihan'])) ?>
            <?php if ($isNow): ?><span class="badge badge-warning" style="margin-left:4px;">kini</span><?php endif; ?>
          </span>
          <span style="font-size:13px;font-weight:700;color:var(--navy);"><?= format_bytes($b) ?></span>
        </div>
        <div class="pbar"><span style="width:<?= $pct ?>%"></span></div>
        <?php if ((int)$r['uptime_seconds'] > 0): ?>
          <div style="font-size:11px;color:var(--muted);margin-top:5px;">
            <i class="fas fa-clock"></i> Online <?= format_uptime_seconds((int)$r['uptime_seconds']) ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="pcard" style="background:#f8fafc;border-style:dashed;">
  <div class="irow" style="border:0;padding:2px;">
    <div class="ir-ic ic-blue"><i class="fas fa-info-circle"></i></div>
    <div class="ir-main">
      <div class="ir-s">Angka pemakaian dihitung dari sesi koneksi internet Anda dan diperbarui berkala oleh sistem.</div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
