<?php
// ============================================================
//  MONITORING · Modul ONU — Tampilan (list + detail)
//  ?dev=<device_id>  → detail perangkat, selain itu → daftar.
// ============================================================
require_once __DIR__ . '/../../_data.php';
$active   = 'onu';
$MOD_URL  = MONITORING_URL . 'modules/onu/';

$dev = isset($_GET['dev']) ? (string)$_GET['dev'] : '';

// ============================================================
//  DETAIL PERANGKAT
// ============================================================
if ($dev !== '') {
    $d = mon_onu_detail($dev);
    $page_title = $d ? ('ONU · ' . $d['pelanggan']) : 'Detail ONU';

    ob_start();
    ?>
    <a href="<?= $MOD_URL ?>form.php" style="display:inline-flex;align-items:center;gap:7px;color:var(--ink2);text-decoration:none;font-size:13px;font-weight:700;margin-bottom:14px">
      <i class="fas fa-arrow-left"></i> Kembali ke Daftar ONU
    </a>

    <?php if (!$d): ?>
      <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:46px 24px;box-shadow:var(--shadow);text-align:center">
        <div style="font-size:34px;color:var(--muted);margin-bottom:8px"><i class="fas fa-question-circle"></i></div>
        <div style="font-size:17px;font-weight:800;color:var(--navy)">Perangkat tidak ditemukan</div>
        <p style="color:var(--ink2);font-size:14px;margin-top:6px">ONU dengan ID tersebut tidak ada di cache. Mungkin sudah dihapus atau belum tersinkron.</p>
      </div>
    <?php
    else:
        [$rxCol, $rxTxt] = mon_rx_style($d['rx']);
        $tempF = $d['temp'] !== null ? (float)$d['temp'] : null;
        [$tCol, $tLbl] = mon_temp_style($tempF);
        $stMap = ['online' => ['--green', 'Online'], 'offline' => ['--red', 'Offline'], 'isolir' => ['--amber', 'Isolir']];
        [$stCol, $stTxt] = $stMap[$d['status']];
    ?>
    <style>
      .dt-head{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:20px 22px;margin-bottom:14px;display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap}
      .dt-ava{width:52px;height:52px;border-radius:13px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:22px;
              background:linear-gradient(135deg,#0f2744,#173257);color:#fbbf24}
      .dt-head h2{margin:0;font-size:19px;font-weight:800;color:var(--navy)}
      .dt-meta{display:flex;gap:14px;flex-wrap:wrap;margin-top:6px;font-size:12.5px;color:var(--ink2)}
      .dt-meta span{display:inline-flex;align-items:center;gap:6px}
      .dt-meta i{color:var(--muted)}
      .dt-badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;padding:5px 12px;border-radius:999px;margin-left:auto}
      .dt-badge .d{width:7px;height:7px;border-radius:50%}
      .dt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:14px}
      .dt-tile{background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:15px 16px}
      .dt-tile .k{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:flex;align-items:center;gap:6px}
      .dt-tile .v{font-size:22px;font-weight:800;margin-top:5px;line-height:1.1;font-variant-numeric:tabular-nums}
      .dt-tile .s{font-size:11.5px;font-weight:600;color:var(--muted);margin-top:2px}
      .dt-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);overflow:hidden}
      .dt-card h3{margin:0;padding:14px 18px;font-size:13.5px;font-weight:800;color:var(--navy);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:8px}
      table.dt{width:100%;border-collapse:collapse;font-size:13.5px}
      table.dt td{padding:11px 18px;border-bottom:1px solid var(--line);vertical-align:middle}
      table.dt tr:last-child td{border-bottom:none}
      table.dt td.k{color:var(--muted);font-weight:600;width:42%;white-space:nowrap}
      table.dt td.v{color:var(--ink);font-weight:600;word-break:break-word}
      .dt-reveal{cursor:pointer;color:var(--amber);font-size:11px;font-weight:700;margin-left:8px;user-select:none}
      .dt-mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px}
    </style>

    <div class="dt-head">
      <div class="dt-ava"><i class="fas fa-broadcast-tower"></i></div>
      <div style="min-width:0">
        <h2><?= clean($d['pelanggan']) ?></h2>
        <div class="dt-meta">
          <?php if ($d['pppoe']): ?><span><i class="fas fa-user-tag"></i> <?= clean($d['pppoe']) ?></span><?php endif; ?>
          <span><i class="fas fa-map-marker-alt"></i> <?= clean($d['area']) ?></span>
          <?php if ($d['no_hp']): ?><span><i class="fas fa-phone"></i> <?= clean($d['no_hp']) ?></span><?php endif; ?>
          <?php if ($d['alamat']): ?><span><i class="fas fa-home"></i> <?= clean($d['alamat']) ?></span><?php endif; ?>
        </div>
      </div>
      <span class="dt-badge" style="background:color-mix(in srgb,var(<?= $stCol ?>) 12%,#fff);color:var(<?= $stCol ?>)">
        <span class="d" style="background:var(<?= $stCol ?>)"></span><?= $stTxt ?>
      </span>
    </div>

    <div class="dt-grid">
      <div class="dt-tile">
        <div class="k"><i class="fas fa-signal" style="color:var(<?= $rxCol ?>)"></i> Optical RX</div>
        <div class="v" style="color:var(<?= $rxCol ?>)"><?= $rxTxt ?><?php if ($d['rx'] !== null): ?> <span style="font-size:13px;color:var(--muted)">dBm</span><?php endif; ?></div>
      </div>
      <div class="dt-tile">
        <div class="k"><i class="fas fa-thermometer-half" style="color:var(<?= $tCol ?>)"></i> Suhu</div>
        <div class="v" style="color:var(<?= $tCol ?>)"><?= $tempF !== null ? number_format($tempF, 1) . '°' : '—' ?></div>
        <div class="s"><?= $tLbl ?></div>
      </div>
      <div class="dt-tile">
        <div class="k"><i class="fas fa-clock"></i> Uptime Perangkat</div>
        <div class="v" style="font-size:16px;color:var(--ink)"><?= $d['uptime_dev'] ? clean($d['uptime_dev']) : '—' ?></div>
      </div>
      <div class="dt-tile">
        <div class="k"><i class="fas fa-link"></i> Uptime PPPoE</div>
        <div class="v" style="font-size:16px;color:var(--ink)"><?= $d['uptime_ppp'] ? clean($d['uptime_ppp']) : '—' ?></div>
      </div>
      <div class="dt-tile">
        <div class="k"><i class="fas fa-laptop"></i> Perangkat Terhubung</div>
        <div class="v" style="color:var(--ink)"><?= $d['devices'] !== null ? clean($d['devices']) : '—' ?></div>
      </div>
      <div class="dt-tile">
        <div class="k"><i class="fas fa-wave-square"></i> Mode PON</div>
        <div class="v" style="font-size:16px;color:var(--ink)"><?= $d['ponmode'] ? clean($d['ponmode']) : '—' ?></div>
      </div>
    </div>

    <div class="dt-card">
      <h3><i class="fas fa-microchip" style="color:var(--muted)"></i> Info Perangkat</h3>
      <table class="dt">
        <tr><td class="k">Manufacturer</td><td class="v"><?= $d['manufacturer'] ? clean($d['manufacturer']) : '—' ?></td></tr>
        <tr><td class="k">Model</td><td class="v"><?= $d['model'] ? clean($d['model']) : '—' ?></td></tr>
        <tr><td class="k">Serial Number</td><td class="v dt-mono"><?= $d['serial'] ? clean($d['serial']) : '—' ?></td></tr>
        <tr><td class="k">Versi Hardware</td><td class="v"><?= $d['hw_ver'] ? clean($d['hw_ver']) : '—' ?></td></tr>
        <tr><td class="k">Versi Software</td><td class="v dt-mono"><?= $d['sw_ver'] ? clean($d['sw_ver']) : '—' ?></td></tr>
        <tr><td class="k">IP Lokal (TR-069)</td><td class="v dt-mono"><?= $d['ip_lokal'] ? clean($d['ip_lokal']) : '—' ?></td></tr>
        <?php if ($d['wifi_ssid']): ?>
        <tr><td class="k">Nama WiFi (SSID)</td><td class="v"><i class="fas fa-wifi" style="color:var(--amber);font-size:11px;margin-right:6px"></i><?= clean($d['wifi_ssid']) ?></td></tr>
        <?php endif; ?>
        <?php if ($d['wifi_pass']): ?>
        <tr><td class="k">Password WiFi</td><td class="v dt-mono">
          <span id="wpMask">••••••••</span>
          <span id="wpReal" style="display:none"><?= clean($d['wifi_pass']) ?></span>
          <span class="dt-reveal" onclick="var m=document.getElementById('wpMask'),r=document.getElementById('wpReal');var s=m.style.display==='none';m.style.display=s?'':'none';r.style.display=s?'none':'';this.textContent=s?'Lihat':'Sembunyikan';">Lihat</span>
        </td></tr>
        <?php endif; ?>
        <tr><td class="k">Last Inform</td><td class="v"><?= $d['last_inform'] ? tgl_indo($d['last_inform'], true) : '—' ?></td></tr>
        <tr><td class="k">Sinkron Cache</td><td class="v"><?= $d['synced_at'] ? tgl_indo($d['synced_at'], true) : '—' ?></td></tr>
        <tr><td class="k">Device ID</td><td class="v dt-mono" style="font-size:11.5px;color:var(--ink2)"><?= clean($d['device_id']) ?></td></tr>
      </table>
    </div>
    <?php endif;
    $content = ob_get_clean();
    require __DIR__ . '/../../layout.php';
    return;
}

// ============================================================
//  DAFTAR ONU
// ============================================================
$page_title = 'Daftar ONU';
$list = mon_onu_list();

$count = ['all' => count($list), 'online' => 0, 'offline' => 0, 'isolir' => 0];
$areas = [];
foreach ($list as $r) {
    $count[$r['status']]++;
    if ($r['area'] !== '—') $areas[$r['area']] = true;
}
ksort($areas);

$badge = ['online' => ['--green', 'Online'], 'offline' => ['--red', 'Offline'], 'isolir' => ['--amber', 'Isolir']];

ob_start();
?>
<style>
  .df-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
  .df-chip{flex:1;min-width:120px;background:var(--card);border:1px solid var(--line);border-radius:13px;
           padding:12px 15px;box-shadow:var(--shadow);cursor:pointer;text-align:left;transition:.14s;position:relative}
  .df-chip:hover{transform:translateY(-1px)}
  .df-chip.on{border-color:var(--navy);box-shadow:0 0 0 2px rgba(15,39,68,.09),var(--shadow)}
  .df-chip .lbl{font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:flex;align-items:center;gap:6px}
  .df-chip .num{font-size:24px;font-weight:800;margin-top:3px;line-height:1}
  .df-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);overflow:hidden}
  .df-bar{display:flex;gap:10px;align-items:center;padding:13px 16px;border-bottom:1px solid var(--line);flex-wrap:wrap}
  .df-search{flex:1;min-width:200px;position:relative}
  .df-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px}
  .df-search input{width:100%;padding:9px 13px 9px 34px;border:1px solid var(--line);border-radius:10px;font-size:13.5px;font-family:inherit;color:var(--ink);background:#f8fafc}
  .df-search input:focus{outline:none;border-color:var(--amber);background:#fff}
  .df-sel{padding:9px 12px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-family:inherit;color:var(--ink2);background:#f8fafc;font-weight:600}
  .df-count{font-size:12.5px;color:var(--muted);font-weight:600;white-space:nowrap;margin-left:auto}
  .df-count b{color:var(--ink)}
  .df-scroll{overflow-x:auto}
  table.df{width:100%;border-collapse:collapse;font-size:13.5px}
  table.df th{text-align:left;padding:11px 16px;font-size:11px;font-weight:700;color:var(--muted);
              text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--line);white-space:nowrap;background:#fbfcfe}
  table.df td{padding:11px 16px;border-bottom:1px solid var(--line);vertical-align:middle}
  table.df tbody tr{cursor:pointer;transition:background .12s}
  table.df tbody tr:hover{background:#f5f8fc}
  table.df tbody tr:last-child td{border-bottom:none}
  .df-name{font-weight:700;color:var(--ink)}
  .df-ppp{font-size:11.5px;color:var(--muted);margin-top:1px}
  .df-badge{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:999px}
  .df-badge .d{width:6px;height:6px;border-radius:50%}
  .df-rx{font-weight:700;font-variant-numeric:tabular-nums}
  .df-rx small{font-weight:600;color:var(--muted);font-size:10.5px;margin-left:2px}
  .df-time{color:var(--ink2);font-variant-numeric:tabular-nums;white-space:nowrap}
  .df-chev{color:var(--muted);text-align:right}
  .df-empty{padding:40px;text-align:center;color:var(--muted);font-size:14px;display:none}
  .df-model{color:var(--ink2);font-weight:600}
</style>

<div class="df-stats" id="dfStats">
  <?php
  $chips = [
      'all'     => ['Total ONU', 'fa-server',    '--ink'],
      'online'  => ['Online',    'fa-circle',    '--green'],
      'offline' => ['Offline',   'fa-circle',    '--red'],
      'isolir'  => ['Isolir',    'fa-circle',    '--amber'],
  ];
  foreach ($chips as $k => [$lbl, $ic, $col]):
  ?>
    <button class="df-chip <?= $k === 'all' ? 'on' : '' ?>" data-status="<?= $k ?>">
      <span class="lbl"><i class="fas <?= $ic ?>" style="color:var(<?= $col ?>);font-size:<?= $ic === 'fa-circle' ? '8px' : '11px' ?>"></i> <?= $lbl ?></span>
      <span class="num" style="color:var(<?= $col ?>)"><?= $count[$k] ?></span>
    </button>
  <?php endforeach; ?>
</div>

<div class="df-card">
  <div class="df-bar">
    <div class="df-search">
      <i class="fas fa-search"></i>
      <input type="text" id="dfSearch" placeholder="Cari nama, PPPoE, atau model ONU…" autocomplete="off">
    </div>
    <select class="df-sel" id="dfArea">
      <option value="">Semua area</option>
      <?php foreach (array_keys($areas) as $a): ?>
        <option value="<?= clean($a) ?>"><?= clean($a) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="df-count" id="dfCount"><b><?= $count['all'] ?></b> ONU</span>
  </div>

  <div class="df-scroll">
    <table class="df">
      <thead>
        <tr>
          <th>Pelanggan</th>
          <th>Area</th>
          <th>Model</th>
          <th>Status</th>
          <th>RXPower</th>
          <th>Last Inform</th>
          <th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody id="dfBody">
        <?php foreach ($list as $r):
            [$rxCol, $rxTxt] = mon_rx_style($r['rx']);
            [$stCol, $stTxt] = $badge[$r['status']];
            $href = $MOD_URL . 'form.php?dev=' . urlencode($r['device_id']);
            $searchKey = strtolower($r['pelanggan'] . ' ' . $r['pppoe'] . ' ' . $r['model'] . ' ' . $r['area']);
        ?>
        <tr data-status="<?= $r['status'] ?>" data-area="<?= clean($r['area']) ?>" data-search="<?= clean($searchKey) ?>"
            onclick="location.href='<?= $href ?>'">
          <td>
            <div class="df-name"><?= clean($r['pelanggan']) ?></div>
            <?php if ($r['pppoe']): ?><div class="df-ppp"><i class="fas fa-user-tag" style="font-size:9px"></i> <?= clean($r['pppoe']) ?></div><?php endif; ?>
          </td>
          <td class="df-model"><?= clean($r['area']) ?></td>
          <td class="df-model"><?= clean($r['model']) ?></td>
          <td>
            <span class="df-badge" style="background:color-mix(in srgb,var(<?= $stCol ?>) 12%,#fff);color:var(<?= $stCol ?>)">
              <span class="d" style="background:var(<?= $stCol ?>)"></span><?= $stTxt ?>
            </span>
          </td>
          <td><span class="df-rx" style="color:var(<?= $rxCol ?>)"><?= $rxTxt ?><?php if ($r['rx'] !== null): ?><small>dBm</small><?php endif; ?></span></td>
          <td class="df-time"><?= $r['last_inform'] ? tgl_indo($r['last_inform'], true) : '—' ?></td>
          <td class="df-act">
            <button type="button" class="df-actbtn"
                    data-dev="<?= clean($r['device_id']) ?>"
                    data-nama="<?= clean($r['pelanggan']) ?>"
                    data-ppp="<?= clean($r['pppoe']) ?>"
                    onclick="event.stopPropagation(); onuOpenAksi(this)">
              <i class="fas fa-sliders-h"></i> Aksi
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="df-empty" id="dfEmpty"><i class="fas fa-inbox" style="font-size:22px;display:block;margin-bottom:8px"></i>Tidak ada ONU yang cocok dengan filter.</div>
  </div>
</div>

<!-- ── Modal Aksi Perangkat (tahap tampilan, aksi belum aktif) ── -->
<style>
  .df-act{text-align:right}
  .df-actbtn{display:inline-flex;align-items:center;gap:6px;font-family:inherit;font-size:12px;font-weight:700;
             color:var(--navy);background:#fff;border:1px solid var(--line);border-radius:9px;padding:6px 12px;cursor:pointer;transition:.12s;white-space:nowrap}
  .df-actbtn:hover{border-color:var(--amber);background:#fffbeb;color:var(--warn)}

  .onu-ov{position:fixed;inset:0;background:rgba(15,39,68,.5);z-index:60;display:none;
          align-items:flex-start;justify-content:center;padding:44px 16px;overflow-y:auto}
  .onu-ov.show{display:flex}
  .onu-modal{background:var(--card);border-radius:18px;box-shadow:0 20px 60px rgba(15,39,68,.35);width:100%;max-width:460px;overflow:hidden}
  .onu-mhead{background:linear-gradient(120deg,#0a1e3d,#0f2744 55%,#173257);color:#fff;padding:18px 20px;display:flex;align-items:flex-start;gap:12px}
  .onu-mhead .ic{width:40px;height:40px;border-radius:11px;background:rgba(245,158,11,.18);color:#fbbf24;
                 display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
  .onu-mhead h3{margin:0;font-size:16px;font-weight:800}
  .onu-mhead p{margin:2px 0 0;font-size:12px;color:rgba(255,255,255,.6);word-break:break-word}
  .onu-mclose{margin-left:auto;background:rgba(255,255,255,.12);border:0;color:#fff;width:30px;height:30px;
              border-radius:8px;cursor:pointer;font-size:16px;line-height:1;flex-shrink:0}
  .onu-mclose:hover{background:rgba(255,255,255,.2)}
  .onu-mbody{padding:20px}
  .onu-fld{margin-bottom:16px}
  .onu-fld label{display:block;font-size:12.5px;font-weight:700;color:var(--ink);margin-bottom:6px}
  .onu-fld label i{color:var(--amber);margin-right:5px}
  .onu-inwrap{position:relative}
  .onu-in{width:100%;padding:10px 13px;border:1px solid var(--line);border-radius:10px;font-size:14px;
          font-family:inherit;color:var(--ink);background:#f8fafc}
  .onu-in:focus{outline:none;border-color:var(--amber);background:#fff;box-shadow:0 0 0 3px rgba(245,158,11,.1)}
  .onu-eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:0;
           color:var(--muted);cursor:pointer;font-size:13px;padding:6px}
  .onu-hint{font-size:11.5px;color:var(--muted);margin-top:5px}
  .onu-warn{display:flex;gap:10px;background:#fffbeb;border:1px solid #fde68a;border-radius:11px;
            padding:11px 13px;font-size:12.5px;color:var(--warn);font-weight:600}
  .onu-warn i{margin-top:1px}
  .onu-mfoot{padding:14px 20px;border-top:1px solid var(--line);display:flex;align-items:center;gap:10px;background:#fbfcfe;flex-wrap:wrap}
  .onu-note{font-size:11.5px;color:var(--muted);font-weight:600;margin-right:auto;display:inline-flex;align-items:center;gap:5px}
  .onu-btn{font-family:inherit;font-size:13px;font-weight:700;padding:9px 16px;border-radius:10px;cursor:pointer;border:1px solid var(--line);background:#fff;color:var(--ink2)}
  .onu-btn.primary{background:var(--navy);color:#fff;border-color:var(--navy)}
  .onu-btn.primary:hover:not(:disabled){background:#173257}
  .onu-btn:disabled{opacity:.6;cursor:not-allowed}
  .onu-msg{display:none;margin-top:14px;padding:10px 13px;border-radius:10px;font-size:12.5px;font-weight:600}
  .onu-msg.show{display:block}
  .onu-msg.err{background:#fef2f2;border:1px solid #fecaca;color:var(--red)}
  .onu-msg.ok{background:#f0fdf4;border:1px solid #bbf7d0;color:var(--green)}
</style>

<div class="onu-ov" id="onuAksiOv">
  <div class="onu-modal" role="dialog" aria-modal="true">
    <div class="onu-mhead">
      <div class="ic"><i class="fas fa-sliders-h"></i></div>
      <div style="min-width:0">
        <h3>Aksi Perangkat</h3>
        <p id="onuMWho">—</p>
      </div>
      <button type="button" class="onu-mclose" onclick="onuCloseAksi()" aria-label="Tutup">&times;</button>
    </div>
    <div class="onu-mbody">
      <div class="onu-fld">
        <label><i class="fas fa-wifi"></i> Nama WiFi (SSID)</label>
        <input type="text" class="onu-in" id="onuSsid" placeholder="Nama WiFi baru" maxlength="32" autocomplete="off">
        <div class="onu-hint">Berlaku untuk 2.4GHz &amp; 5GHz.</div>
      </div>
      <div class="onu-fld">
        <label><i class="fas fa-key"></i> Password WiFi</label>
        <div class="onu-inwrap">
          <input type="password" class="onu-in" id="onuPass" placeholder="Password WiFi baru" maxlength="63" autocomplete="new-password" style="padding-right:38px">
          <button type="button" class="onu-eye" onclick="onuTogglePass(this)" aria-label="Lihat password"><i class="fas fa-eye"></i></button>
        </div>
        <div class="onu-hint">Minimal 8 karakter (WPA2).</div>
      </div>
      <div class="onu-warn"><i class="fas fa-exclamation-triangle"></i> Saat diterapkan, semua perangkat yang terhubung ke WiFi ini akan terputus sebentar lalu tersambung kembali.</div>
      <div class="onu-msg" id="onuMsg"></div>
      <input type="hidden" id="onuCsrf" value="<?= csrf_token() ?>">
    </div>
    <div class="onu-mfoot">
      <span class="onu-note"><i class="fas fa-bolt"></i> Langsung ke perangkat</span>
      <button type="button" class="onu-btn" onclick="onuCloseAksi()">Batal</button>
      <button type="button" class="onu-btn primary" id="onuApplyBtn" onclick="onuApply()">Terapkan</button>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$body_extra = <<<'HTML'
<script>
(function(){
  var rows   = Array.prototype.slice.call(document.querySelectorAll('#dfBody tr'));
  var search = document.getElementById('dfSearch');
  var area   = document.getElementById('dfArea');
  var count  = document.getElementById('dfCount');
  var empty  = document.getElementById('dfEmpty');
  var chips  = Array.prototype.slice.call(document.querySelectorAll('.df-chip'));
  var fStatus = 'all';

  function apply(){
    var q = search.value.trim().toLowerCase();
    var a = area.value;
    var shown = 0;
    rows.forEach(function(tr){
      var ok = (fStatus === 'all' || tr.dataset.status === fStatus)
            && (!a || tr.dataset.area === a)
            && (!q || tr.dataset.search.indexOf(q) !== -1);
      tr.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    count.innerHTML = '<b>' + shown + '</b> ONU';
    empty.style.display = shown ? 'none' : 'block';
  }

  chips.forEach(function(c){
    c.addEventListener('click', function(){
      fStatus = c.dataset.status;
      chips.forEach(function(x){ x.classList.toggle('on', x === c); });
      apply();
    });
  });
  search.addEventListener('input', apply);
  area.addEventListener('change', apply);

  // Refresh angka status secara live (badge "Live").
  function poll(){
    fetch('ajax.php', {headers:{'X-Requested-With':'fetch'}})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || !d.counts) return;
        chips.forEach(function(c){
          var n = c.querySelector('.num');
          if (d.counts[c.dataset.status] != null) n.textContent = d.counts[c.dataset.status];
        });
      })
      .catch(function(){});
  }
  setInterval(poll, 30000);

  // ── Modal Aksi Perangkat (tahap tampilan; belum ada eksekusi) ──
  var ov = document.getElementById('onuAksiOv');
  var onuReq = 0;
  var onuCurrentDev = null;
  function onuShowMsg(type, text){
    var m = document.getElementById('onuMsg');
    m.className = 'onu-msg show ' + (type === 'ok' ? 'ok' : 'err');
    m.textContent = text;
  }
  window.onuOpenAksi = function(btn){
    var who = btn.getAttribute('data-nama') || '—';
    var ppp = btn.getAttribute('data-ppp');
    var dev = btn.getAttribute('data-dev');
    onuCurrentDev = dev;
    document.getElementById('onuMWho').textContent = who + (ppp ? ' · ' + ppp : '');
    var msg = document.getElementById('onuMsg'); msg.className = 'onu-msg'; msg.textContent = '';
    var ab = document.getElementById('onuApplyBtn'); ab.disabled = false; ab.innerHTML = 'Terapkan';
    var s = document.getElementById('onuSsid');
    var p = document.getElementById('onuPass');
    p.type = 'password'; s.value = ''; p.value = '';
    s.placeholder = 'Memuat…'; p.placeholder = 'Memuat…';
    if (ov) ov.classList.add('show');
    s.focus();
    // Ambil nilai WiFi saat ini (read-only) untuk prefill.
    var my = ++onuReq;
    fetch('ajax.php?wifi=' + encodeURIComponent(dev), {headers:{'X-Requested-With':'fetch'}})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (my !== onuReq) return;
        s.placeholder = 'Nama WiFi baru'; p.placeholder = 'Password WiFi baru';
        if (d && d.ok){
          if (d.ssid != null) s.value = d.ssid;
          if (d.pass != null) p.value = d.pass;
        }
      })
      .catch(function(){
        if (my !== onuReq) return;
        s.placeholder = 'Nama WiFi baru'; p.placeholder = 'Password WiFi baru';
      });
  };
  window.onuCloseAksi = function(){ if (ov) ov.classList.remove('show'); };
  window.onuApply = function(){
    var s = document.getElementById('onuSsid'), p = document.getElementById('onuPass');
    var ssid = s.value.trim(), pass = p.value;
    if (!ssid)            { onuShowMsg('err', 'Nama WiFi wajib diisi.'); s.focus(); return; }
    if (ssid.length > 32) { onuShowMsg('err', 'Nama WiFi maksimal 32 karakter.'); s.focus(); return; }
    if (pass !== '' && (pass.length < 8 || pass.length > 63)) {
      onuShowMsg('err', 'Password WiFi harus 8–63 karakter (atau kosongkan bila tidak ingin mengubah).'); p.focus(); return;
    }
    var who = document.getElementById('onuMWho').textContent;
    if (!confirm('Terapkan perubahan WiFi ke "' + who + '"?\n\nSemua perangkat yang terhubung akan terputus sebentar.')) return;

    var btn = document.getElementById('onuApplyBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim…';
    document.getElementById('onuMsg').className = 'onu-msg';

    var body = new URLSearchParams();
    body.set('action', 'set_wifi');
    body.set('csrf_token', document.getElementById('onuCsrf').value);
    body.set('device_id', onuCurrentDev || '');
    body.set('ssid', ssid);
    body.set('pass', pass);

    fetch('act.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: body.toString()
    })
      .then(function(r){ return r.json(); })
      .then(function(d){
        btn.disabled = false; btn.innerHTML = 'Terapkan';
        if (d && d.ok) { onuShowMsg('ok', d.msg || 'Berhasil dikirim.'); setTimeout(window.onuCloseAksi, 3000); }
        else           { onuShowMsg('err', (d && d.msg) || 'Gagal mengirim perubahan.'); }
      })
      .catch(function(){
        btn.disabled = false; btn.innerHTML = 'Terapkan';
        onuShowMsg('err', 'Gagal terhubung ke server.');
      });
  };
  window.onuTogglePass = function(b){
    var inp = document.getElementById('onuPass'), ic = b.querySelector('i');
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    ic.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
  };
  if (ov) ov.addEventListener('click', function(e){ if (e.target === ov) window.onuCloseAksi(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') window.onuCloseAksi(); });
})();
</script>
HTML;

require __DIR__ . '/../../layout.php';
