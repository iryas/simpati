<?php
// ============================================================
//  PORTAL PELANGGAN — Info & Pengumuman
// ============================================================
require_once __DIR__ . '/_data.php';
portal_auth_check();

$me   = portal_current();
$list = portal_pengumuman(30);

$page_title = 'Info & Pengumuman';
$page_sub   = 'Kabar terbaru dari kami';
$active     = 'info';

ob_start();
?>

<?php if (empty($list)): ?>
  <div class="pcard">
    <div class="empty" style="padding:28px 10px;">
      <i class="fas fa-bullhorn"></i>
      <p>Belum ada pengumuman saat ini.</p>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($list as $p): ?>
    <?php [$warna, $ikon, $tlabel] = pengumuman_meta((string)$p['tipe']); ?>
    <div class="pcard" style="border-left:4px solid <?= $warna ?>;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <span style="width:30px;height:30px;border-radius:8px;flex-shrink:0;
                     display:flex;align-items:center;justify-content:center;
                     background:<?= $warna ?>1a;color:<?= $warna ?>;">
          <i class="fas <?= $ikon ?>"></i>
        </span>
        <div style="flex:1;min-width:0;">
          <div style="font-size:14.5px;font-weight:800;color:var(--navy);line-height:1.25;">
            <?php if ((int)$p['pinned']): ?><i class="fas fa-thumbtack" style="font-size:11px;color:<?= $warna ?>;margin-right:3px;"></i><?php endif; ?>
            <?= clean((string)$p['judul']) ?>
          </div>
          <div style="font-size:11px;color:var(--muted);margin-top:1px;">
            <span style="color:<?= $warna ?>;font-weight:700;"><?= $tlabel ?></span>
            &middot; <?= tgl_indo((string)$p['created_at']) ?>
          </div>
        </div>
      </div>
      <div style="font-size:13px;color:var(--ink);line-height:1.55;">
        <?= nl2br(clean((string)$p['isi'])) ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
