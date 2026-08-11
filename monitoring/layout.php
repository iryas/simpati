<?php
// ============================================================
//  MONITORING JARINGAN — Layout / Shell
//  Variabel: $content, $page_title, $active
// ============================================================
require_once __DIR__ . '/_init.php';

$me       = current_user();
$nama_isp = clean(app_setting('nama_isp', 'KahfiNet'));
$active   = $active ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= clean($page_title ?? 'Monitoring') ?> — <?= $nama_isp ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box}
    :root{
      --bg:#eaeff5; --card:#fff; --line:#e6ebf2; --ink:#0f2744; --ink2:#475569; --muted:#94a3b8;
      --navy:#0f2744; --amber:#f59e0b; --green:#16a34a; --red:#dc2626; --warn:#b45309;
      --shadow:0 1px 2px rgba(15,39,68,.05),0 10px 26px rgba(15,39,68,.06);
    }
    body{margin:0;background:var(--bg);color:var(--ink);font-family:'Inter',sans-serif;-webkit-font-smoothing:antialiased}
    .msticky{position:sticky;top:0;z-index:20}
    .mtop{background:linear-gradient(120deg,#0a1e3d,#0f2744 55%,#173257);color:#fff}
    .mnav{background:#fff;border-bottom:1px solid var(--line)}
    .mnav-in{max-width:1180px;margin:0 auto;padding:0 12px;display:flex;gap:2px;overflow-x:auto}
    .mnav-item{display:inline-flex;align-items:center;gap:7px;padding:13px 16px;font-size:13.5px;font-weight:700;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent;white-space:nowrap}
    .mnav-item:hover{color:var(--ink2)}
    .mnav-item.active{color:var(--navy);border-bottom-color:var(--amber)}
    .mtop-in{max-width:1180px;margin:0 auto;padding:13px 22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
    .mbrand{display:flex;align-items:center;gap:11px;margin-right:auto}
    .mlogo{width:38px;height:38px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,#f59e0b,#fbbf24);
           color:#0f2744;display:flex;align-items:center;justify-content:center;font-size:17px;
           box-shadow:0 8px 20px rgba(245,158,11,.34)}
    .mbrand h1{font-size:15.5px;font-weight:800;margin:0;letter-spacing:.2px}
    .mbrand p{margin:1px 0 0;font-size:11.5px;color:rgba(255,255,255,.55)}
    .mclock{font-variant-numeric:tabular-nums;font-size:12.5px;color:rgba(255,255,255,.75)}
    .mlive{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;
           background:rgba(22,163,74,.18);color:#7ee2a0;border:1px solid rgba(126,226,160,.28);padding:5px 11px;border-radius:999px}
    .mdot{width:7px;height:7px;border-radius:50%;background:#22c55e;animation:mp 2s infinite}
    @keyframes mp{0%{box-shadow:0 0 0 0 rgba(34,197,94,.5)}70%{box-shadow:0 0 0 6px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
    @media(prefers-reduced-motion:reduce){.mdot{animation:none}}
    .mbtn{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;color:#fff;text-decoration:none;
          background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);padding:7px 13px;border-radius:9px}
    .mbtn:hover{background:rgba(255,255,255,.16)}
    .muser{display:flex;align-items:center;gap:8px;font-size:12.5px;color:rgba(255,255,255,.85)}
    .mrole{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
           background:rgba(245,158,11,.2);color:#fbbf24;padding:2px 7px;border-radius:6px}
    .mmain{max-width:1180px;margin:0 auto;padding:22px}
    /* Utility jarak ikon→teks (shell monitoring tak memuat Bootstrap). */
    .mr-1{margin-right:7px}
    .mr-2{margin-right:9px}
  </style>
</head>
<body>
  <div class="msticky">
  <header class="mtop">
    <div class="mtop-in">
      <div class="mbrand">
        <div class="mlogo"><i class="fas fa-heartbeat"></i></div>
        <div><h1>Monitoring Jaringan</h1><p><?= $nama_isp ?></p></div>
      </div>
      <span class="mclock" id="mclock">—</span>
      <span class="mlive"><span class="mdot"></span> Live</span>
      <span class="muser"><i class="fas fa-user-circle"></i> <?= clean($me['nama']) ?> <span class="mrole"><?= clean($me['role']) ?></span></span>
      <a href="<?= BASE_URL ?>index.php" class="mbtn"><i class="fas fa-arrow-left"></i> Kembali ke Admin</a>
      <a href="<?= BASE_URL ?>act.php?action=logout" class="mbtn" onclick="return confirm('Keluar dari sesi?')"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </header>
  <nav class="mnav">
    <div class="mnav-in">
      <?php
      $navItems = [
          'overview'  => ['index.php',                    'fa-chart-pie',      'Overview'],
          'onu'       => ['modules/onu/form.php',         'fa-list-ul',        'Daftar ONU'],
          'offline'   => ['modules/offline/form.php',     'fa-wifi',           'Offline'],
          'signal'    => ['modules/signal/form.php',      'fa-signal',         'Sinyal'],
          'pemakaian' => ['modules/pemakaian/form.php',   'fa-tachometer-alt', 'Pemakaian'],
      ];
      foreach ($navItems as $key => [$url, $ic, $label]):
      ?>
        <a href="<?= MONITORING_URL . $url ?>" class="mnav-item <?= $active === $key ? 'active' : '' ?>"><i class="fas <?= $ic ?>"></i> <?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
  </div>

  <main class="mmain"><?= $content ?? '' ?></main>

  <script>
    (function(){
      function p(n){return String(n).padStart(2,'0')}
      function t(){var d=new Date();var el=document.getElementById('mclock');
        if(el)el.textContent=p(d.getHours())+':'+p(d.getMinutes())+':'+p(d.getSeconds());}
      t();setInterval(t,1000);
    })();
  </script>
  <?= $body_extra ?? '' ?>
</body>
</html>
