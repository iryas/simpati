<?php
// ============================================================
//  MONITORING JARINGAN — Beranda / Overview (grafik pie)
// ============================================================
require_once __DIR__ . '/_data.php';

$me         = current_user();
$page_title = 'Monitoring Jaringan';
$active     = 'overview';

$o          = mon_overview();
$area       = mon_pelanggan_area();
$statusArea = mon_status_per_area();
$topUsage   = mon_top_pemakaian(10);
$panelH     = max(240, max(1, count($area), count($statusArea), count($topUsage)) * 36 + 40);

// Buang entri 0 biar legend bersih.
$nz = fn(array $a) => array_filter($a, fn($v) => $v > 0);
$jenis  = $nz($o['jenis']);
$status = $nz($o['status']);
$rx     = $nz($o['rx']);
$temp   = $nz($o['temp']);

$online   = $o['status']['Online'] ?? 0;
$offline  = $o['status']['Offline'] ?? 0;
$lemah    = ($o['rx']['Waspada'] ?? 0) + ($o['rx']['Kritis'] ?? 0);

// Warna semantik + palet kategori (untuk jenis ONU).
$C = [
    'Online' => '#16a34a', 'Offline' => '#dc2626',
    'Bagus' => '#16a34a', 'Waspada' => '#f59e0b', 'Kritis' => '#dc2626', 'Terlalu kuat' => '#0ea5e9', 'Tak ada data' => '#94a3b8',
    'Normal' => '#16a34a', 'Hangat' => '#f59e0b', 'Panas' => '#dc2626',
];
$palette = ['#0f2744', '#f59e0b', '#0ea5e9', '#16a34a', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b'];
$i = 0;
foreach ($jenis as $k => $v) { if (!isset($C[$k])) $C[$k] = $palette[$i++ % count($palette)]; }

ob_start();
?>

<!-- Ringkasan -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px">
  <?php
  $tiles = [
      ['fa-server', 'Total ONU', $o['total'], 'var(--ink2)'],
      ['fa-circle-check', 'Online', $online, 'var(--green)'],
      ['fa-wifi', 'Offline', $offline, 'var(--red)'],
      ['fa-triangle-exclamation', 'Sinyal lemah', $lemah, 'var(--warn)'],
  ];
  foreach ($tiles as [$ic, $lbl, $val, $col]): ?>
    <div style="background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 18px;box-shadow:var(--shadow)">
      <div style="font-size:12.5px;color:var(--muted);font-weight:600"><i class="fas <?= $ic ?> mr-1"></i><?= $lbl ?></div>
      <div style="font-size:28px;font-weight:800;color:<?= $col ?>;line-height:1;margin-top:6px"><?= (int)$val ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Grafik pie -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
  <?php
  $charts = [
      ['jenisChart', 'Jenis / Model ONU', 'fa-microchip'],
      ['statusChart', 'Status ONU', 'fa-wifi'],
      ['rxChart', 'Optical RX (kualitas sinyal)', 'fa-signal'],
      ['tempChart', 'Temperatur ONU', 'fa-temperature-half'],
  ];
  foreach ($charts as [$id, $judul, $ic]): ?>
    <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px 18px 8px;box-shadow:var(--shadow)">
      <div style="font-size:13.5px;font-weight:800;color:var(--navy);margin-bottom:10px"><i class="fas <?= $ic ?> mr-2" style="color:var(--amber)"></i><?= $judul ?></div>
      <div style="position:relative;height:230px"><canvas id="<?= $id ?>"></canvas></div>
    </div>
  <?php endforeach; ?>
</div>

<style>
  .mtab{border:0;background:none;font-family:inherit;font-size:13px;font-weight:700;color:var(--muted);
        padding:11px 15px;border-bottom:2px solid transparent;cursor:pointer;margin-bottom:-1px;white-space:nowrap}
  .mtab:hover{color:var(--ink2)}
  .mtab.active{color:var(--navy);border-bottom-color:var(--amber)}
</style>

<!-- Analisa area & pemakaian (tab) -->
<div style="background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);margin-top:16px;overflow:hidden">
  <div style="display:flex;gap:2px;padding:8px 10px 0;border-bottom:1px solid var(--line);overflow-x:auto">
    <button class="mtab active" data-tab="area"><i class="fas fa-map-marked-alt mr-1"></i>Sebaran per Area</button>
    <button class="mtab" data-tab="status"><i class="fas fa-signal mr-1"></i>Status per Area</button>
    <button class="mtab" data-tab="usage"><i class="fas fa-tachometer-alt mr-1"></i>Top Pemakaian</button>
  </div>
  <div class="mtabpanel" data-panel="area" style="padding:16px 18px">
    <div style="position:relative;height:<?= $panelH ?>px"><canvas id="areaChart"></canvas></div>
  </div>
  <div class="mtabpanel" data-panel="status" style="display:none;padding:16px 18px">
    <div style="position:relative;height:<?= $panelH ?>px"><canvas id="statusAreaChart"></canvas></div>
  </div>
  <div class="mtabpanel" data-panel="usage" style="display:none;padding:16px 18px">
    <div style="position:relative;height:<?= $panelH ?>px"><canvas id="topUsageChart"></canvas></div>
  </div>
</div>

<div style="text-align:center;color:var(--muted);font-size:12px;margin:20px 0 8px">
  <i class="fas fa-database mr-1"></i> Data dari cache GenieACS (snapshot sync terakhir) · <?= $o['total'] ?> ONU
</div>

<?php
$content = ob_get_clean();

$body_extra = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>';
$body_extra .= '<script>' . '
  var CMAP = ' . json_encode($C) . ';
  function pie(id, obj){
    var labels = Object.keys(obj), data = labels.map(function(k){return obj[k]});
    new Chart(document.getElementById(id), {
      type:"doughnut",
      data:{ labels:labels, datasets:[{ data:data,
        backgroundColor: labels.map(function(l){return CMAP[l]||"#94a3b8"}),
        borderColor:"#fff", borderWidth:2 }] },
      options:{ responsive:true, maintainAspectRatio:false, cutout:"56%",
        plugins:{ legend:{ position:"bottom", labels:{ boxWidth:12, font:{size:12}, padding:12 } } } }
    });
  }
  pie("jenisChart",  ' . json_encode($jenis)  . ');
  pie("statusChart", ' . json_encode($status) . ');
  pie("rxChart",     ' . json_encode($rx)     . ');
  pie("tempChart",   ' . json_encode($temp)   . ');
  function barh(id, obj, color, unit){
    var labels = Object.keys(obj), data = labels.map(function(k){return obj[k]});
    new Chart(document.getElementById(id), {
      type:"bar",
      data:{ labels:labels, datasets:[{ data:data, backgroundColor:color||"#f59e0b", borderRadius:6, maxBarThickness:26 }] },
      options:{ indexAxis:"y", responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label:function(c){return " "+c.parsed.x+(unit?" "+unit:"")} } } },
        scales:{ x:{ beginAtZero:true, ticks:{precision:0, callback:function(v){return v+(unit?" "+unit:"")}}, grid:{color:"#eef1f7"} }, y:{ grid:{display:false} } } }
    });
  }
  function stackedbar(id, obj){
    var labels = Object.keys(obj);
    var series = {Online:"#16a34a", Offline:"#dc2626", Isolir:"#f59e0b"};
    var datasets = Object.keys(series).map(function(name){
      return { label:name, data: labels.map(function(a){return obj[a][name]||0}), backgroundColor:series[name], borderRadius:4, maxBarThickness:24 };
    });
    new Chart(document.getElementById(id), {
      type:"bar", data:{ labels:labels, datasets:datasets },
      options:{ indexAxis:"y", responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{position:"bottom", labels:{boxWidth:12,font:{size:12},padding:12}} },
        scales:{ x:{ stacked:true, beginAtZero:true, ticks:{precision:0}, grid:{color:"#eef1f7"} }, y:{ stacked:true, grid:{display:false} } } }
    });
  }
  barh("areaChart", ' . json_encode($area) . ');
  stackedbar("statusAreaChart", ' . json_encode($statusArea) . ');
  barh("topUsageChart", ' . json_encode($topUsage) . ', "#0ea5e9", "GB");

  document.querySelectorAll(".mtab").forEach(function(btn){
    btn.addEventListener("click", function(){
      var t = btn.getAttribute("data-tab");
      document.querySelectorAll(".mtab").forEach(function(b){ b.classList.toggle("active", b===btn); });
      document.querySelectorAll(".mtabpanel").forEach(function(p){
        var show = p.getAttribute("data-panel") === t;
        p.style.display = show ? "block" : "none";
        if (show) { var cv = p.querySelector("canvas"); var c = cv && Chart.getChart(cv); if (c) c.resize(); }
      });
    });
  });
' . '</script>';

require __DIR__ . '/layout.php';
