<?php
// ============================================================
//  PORTAL PELANGGAN — Layout Utama (mobile-first)
//  Variabel: $page_title, $content, $active
//  Opsional : $head_mode ('top'|'page'), $back_url, $head_extra
// ============================================================
require_once __DIR__ . '/_auth.php';
portal_auth_check();

$me       = portal_current();
$nama_isp = clean(app_setting('nama_isp', 'KahfiNet'));
$active   = $active   ?? '';
$head_mode = $head_mode ?? 'page';
$back_url  = $back_url  ?? (PORTAL_URL . 'index.php');

$nav = [
    ['key' => 'beranda', 'url' => 'index.php',   'ic' => 'fa-home',                'label' => 'Beranda'],
    ['key' => 'tagihan', 'url' => 'tagihan.php', 'ic' => 'fa-file-invoice-dollar', 'label' => 'Tagihan'],
    ['key' => 'usage',   'url' => 'usage.php',   'ic' => 'fa-chart-line',          'label' => 'Pemakaian'],
    ['key' => 'lapor',   'url' => 'lapor.php',   'ic' => 'fa-headset',             'label' => 'Lapor'],
    ['key' => 'akun',    'url' => 'profil.php',  'ic' => 'fa-user',                'label' => 'Akun'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0">
  <meta name="theme-color" content="#0f2744">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="<?= $nama_isp ?>">
  <title><?= clean($page_title ?? 'Portal') ?> — <?= $nama_isp ?></title>
  <link rel="manifest" href="<?= PORTAL_URL ?>manifest.php">
  <link rel="apple-touch-icon" href="<?= PORTAL_URL ?>assets/icon-192.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= PORTAL_URL ?>assets/portal.css?v=<?= filemtime(__DIR__ . '/assets/portal.css') ?>">
  <?= $head_extra ?? '' ?>
</head>
<body>

<?php if ($head_mode === 'top'): ?>
  <header class="topbar">
    <div class="tb-avatar"><i class="fas fa-user"></i></div>
    <div class="tb-info">
      <div class="tb-hi">Selamat datang,</div>
      <div class="tb-name"><?= clean($me['nama']) ?></div>
    </div>
    <a href="<?= PORTAL_URL ?>act.php?action=logout" class="tb-btn"
       onclick="return confirm('Keluar dari portal?')" title="Keluar"
       style="display:flex;align-items:center;justify-content:center;">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </header>
<?php else: ?>
  <header class="page-head">
    <a href="<?= $back_url ?>" class="ph-back" style="display:flex;align-items:center;justify-content:center;">
      <i class="fas fa-arrow-left"></i>
    </a>
    <div>
      <h1><?= clean($page_title ?? 'Portal') ?></h1>
      <?php if (!empty($page_sub)): ?><div class="ph-sub"><?= clean($page_sub) ?></div><?php endif; ?>
    </div>
  </header>
<?php endif; ?>

  <main class="content">
    <?= $content ?? '' ?>
  </main>

  <nav class="bottomnav">
    <?php foreach ($nav as $n): ?>
      <a href="<?= PORTAL_URL . $n['url'] ?>" class="<?= $active === $n['key'] ? 'active' : '' ?>">
        <i class="fas <?= $n['ic'] ?>"></i><span><?= $n['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div id="ptoast" class="ptoast"><i id="ptoastIc" class="fas"></i><span id="ptoastMsg"></span></div>

  <?php render_flash(); ?>
  <script>
    // Toast ringan dari window.__flash (di-set render_flash)
    (function () {
      if (!window.__flash) return;
      var map = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
      var t = document.getElementById('ptoast');
      document.getElementById('ptoastIc').className = 'fas fa-' + (map[window.__flash.type] || 'info-circle');
      document.getElementById('ptoastMsg').textContent = window.__flash.msg;
      t.className = 'ptoast show t-' + window.__flash.type;
      setTimeout(function () { t.className = 'ptoast t-' + window.__flash.type; }, 3500);
    })();

    // Registrasi service worker (PWA)
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= PORTAL_URL ?>sw.js').catch(function () {});
      });
    }
  </script>
  <?= $body_extra ?? '' ?>
</body>
</html>
