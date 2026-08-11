<?php
// ============================================================
//  MONITORING · Modul Signal — Early Warning Sinyal ONU
//  Hanya menampilkan ONU dengan sinyal KRITIS atau WASPADA.
//  Tujuan: tangkap ONU yang mau putus sebelum benar-benar down.
// ============================================================
require_once __DIR__ . '/../../_data.php';
$page_title = 'Sinyal Bermasalah';
$active     = 'signal';
$MOD_URL    = MONITORING_URL . 'modules/signal/';
$ONU_URL    = MONITORING_URL . 'modules/onu/';

$data      = mon_signal_list();
$rows      = $data['rows'];
$counts    = $data['counts'];
$ref       = $data['ref'];
$total     = $data['total'];      // hanya kritis+waspada
$total_all = $data['total_all'];  // semua ONU berareal

// Kumpulkan area untuk dropdown filter.
$areas = [];
foreach ($rows as $r) {
    if ($r['area'] !== '—') $areas[$r['area']] = true;
}
ksort($areas);

// Persen sinyal bermasalah terhadap total ONU.
$persen = $total_all > 0 ? round($total / $total_all * 100) : 0;

ob_start();
?>
<style>
  /* ── Sync bar ── */
  .sg-syncbar{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);margin-bottom:18px;font-weight:600}
  .sg-syncbar b{color:var(--ink2)}
  .sg-ldot{width:7px;height:7px;border-radius:50%;background:#22c55e;animation:mp 2s infinite;display:inline-block}
  @keyframes mp{0%{box-shadow:0 0 0 0 rgba(34,197,94,.5)}70%{box-shadow:0 0 0 5px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
  @media(prefers-reduced-motion:reduce){.sg-ldot{animation:none}}

  /* ── Alert banner ── */
  .sg-alert{display:flex;align-items:center;gap:14px;padding:16px 20px;border-radius:14px;margin-bottom:20px;border:1px solid}
  .sg-alert-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}

  /* ── Stats row ── */
  .sg-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
  .sg-stat{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 18px;box-shadow:var(--shadow)}
  .sg-stat .k{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:flex;align-items:center;gap:6px}
  .sg-stat .v{font-size:28px;font-weight:800;line-height:1;margin-top:6px}
  .sg-stat .s{font-size:11.5px;color:var(--muted);margin-top:3px;font-weight:600}

  /* ── Toolbar ── */
  .sg-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
  .sg-search{flex:1;min-width:220px;position:relative}
  .sg-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none}
  .sg-search input{width:100%;padding:9px 13px 9px 36px;border:1px solid var(--line);border-radius:10px;
                   font-size:13.5px;font-family:inherit;color:var(--ink);background:#f8fafc;transition:.15s}
  .sg-search input:focus{outline:none;border-color:var(--amber);background:#fff;box-shadow:0 0 0 3px rgba(245,158,11,.1)}
  .sg-sel{padding:9px 13px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-family:inherit;
          color:var(--ink2);background:#f8fafc;font-weight:600;cursor:pointer}
  .sg-sel:focus{outline:none;border-color:var(--amber)}
  .sg-filter-btn{padding:9px 13px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-family:inherit;
                 font-weight:700;cursor:pointer;background:#f8fafc;color:var(--ink2);transition:.12s}
  .sg-filter-btn.on{background:var(--navy);color:#fff;border-color:var(--navy)}
  .sg-count{font-size:12.5px;color:var(--muted);font-weight:600;white-space:nowrap;margin-left:auto}
  .sg-count b{color:var(--ink)}
  .sg-refresh{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border-radius:10px;font-size:13px;
              font-weight:700;background:var(--navy);color:#fff;border:none;cursor:pointer;font-family:inherit;transition:.15s}
  .sg-refresh:hover{background:#173257}
  .sg-refresh.spin i{animation:spin .7s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}

  /* ── Table card ── */
  .sg-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);overflow:hidden}
  .sg-scroll{overflow-x:auto}
  table.sg-tbl{width:100%;border-collapse:collapse;font-size:13.5px}
  table.sg-tbl th{text-align:left;padding:11px 16px;font-size:10.5px;font-weight:700;color:var(--muted);
                  text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;background:#fbfcfe;border-bottom:1px solid var(--line)}
  table.sg-tbl td{padding:11px 16px;border-bottom:1px solid var(--line);vertical-align:middle}
  table.sg-tbl tbody tr:last-child td{border-bottom:none}
  table.sg-tbl tbody tr{cursor:pointer;transition:background .1s}
  table.sg-tbl tbody tr:hover{background:#fff8f0}

  .sg-name{font-weight:700;color:var(--ink)}
  .sg-ppp{font-size:11.5px;color:var(--muted);margin-top:1px}
  .sg-area-txt{color:var(--ink2);font-weight:600}
  .sg-model{color:var(--ink2);font-weight:600;font-size:12.5px}

  /* RX visual */
  .sg-rx-wrap{display:flex;flex-direction:column;gap:3px;min-width:140px}
  .sg-rx-num{font-weight:800;font-size:15px;font-variant-numeric:tabular-nums}
  .sg-rx-bar-bg{height:6px;border-radius:999px;background:#eef1f7;width:120px;overflow:hidden}
  .sg-rx-bar{height:6px;border-radius:999px;transition:width .3s}

  /* Category + status badges */
  .sg-kbadge{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px}
  .sg-sbadge{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:999px}
  .sg-sbadge .d{width:6px;height:6px;border-radius:50%}

  .sg-li{color:var(--ink2);font-size:12.5px;white-space:nowrap}
  .sg-empty-row{padding:40px;text-align:center;color:var(--muted);font-size:14px;display:none}

  /* Legend bar */
  .sg-legend{display:flex;gap:16px;flex-wrap:wrap;font-size:11.5px;font-weight:600;color:var(--muted);
             padding:12px 16px;border-top:1px solid var(--line);background:#fbfcfe}
  .sg-legend span{display:inline-flex;align-items:center;gap:5px}
  .sg-ld{width:10px;height:10px;border-radius:3px}

  /* Empty state (all good) */
  .sg-allgood{background:var(--card);border:1px solid #bbf7d0;border-radius:16px;box-shadow:var(--shadow);
              padding:60px 24px;text-align:center}
  .sg-allgood-icon{width:64px;height:64px;border-radius:18px;margin:0 auto 16px;display:flex;align-items:center;
                   justify-content:center;font-size:26px;background:linear-gradient(135deg,#dcfce7,#bbf7d0);color:#16a34a}
</style>

<!-- Sync bar -->
<div class="sg-syncbar">
  <span class="sg-ldot"></span>
  Early warning sinyal ONU · Data cache
  <b id="sgSyncTs"><?= $ref ? date('d M Y, H:i:s', $ref) : '—' ?></b>
  <span id="sgUpdated" style="margin-left:4px"></span>
</div>

<?php if ($total > 0):
  // Alert banner level keparahan.
  $kritisCount = $counts['kritis'];
  if ($kritisCount > 0):
    $alertBg  = '#fef2f2'; $alertBorder = '#fecaca'; $alertIconBg = '#fee2e2';
    $alertIconC = 'var(--red)'; $alertIc = 'fa-exclamation-triangle';
    $alertMsg = "<b>$kritisCount ONU dengan sinyal KRITIS</b> (&lt; −27 dBm) — risiko putus segera, perlu ditindak sekarang!";
  else:
    $alertBg  = '#fffbeb'; $alertBorder = '#fde68a'; $alertIconBg = '#fef3c7';
    $alertIconC = 'var(--amber)'; $alertIc = 'fa-exclamation-circle';
    $alertMsg = "<b>{$counts['waspada']} ONU dengan sinyal WASPADA</b> (−27 s/d −25 dBm) — pantau sebelum kualitas makin menurun.";
  endif;
?>
<!-- Alert banner -->
<div class="sg-alert" style="background:<?= $alertBg ?>;border-color:<?= $alertBorder ?>">
  <div class="sg-alert-icon" style="background:<?= $alertIconBg ?>;color:<?= $alertIconC ?>">
    <i class="fas <?= $alertIc ?>"></i>
  </div>
  <div style="flex:1">
    <div style="font-size:14px;color:var(--ink)"><?= $alertMsg ?></div>
    <div style="font-size:12px;color:var(--muted);margin-top:3px;font-weight:600">
      <?= $total ?> dari <?= $total_all ?> ONU (<?= $persen ?>%) memerlukan perhatian.
      ONU dengan sinyal bagus tidak ditampilkan di sini.
    </div>
  </div>
</div>

<?php endif; ?>

<!-- Stats row -->
<div class="sg-stats">
  <?php
  $stats = [
      ['fa-exclamation-triangle', 'Sinyal Kritis',  $counts['kritis'],        'var(--red)',   '< −27 dBm',            'id="sgStatKritis"'],
      ['fa-exclamation-circle',   'Sinyal Waspada', $counts['waspada'],       'var(--amber)', '−27 s/d −25 dBm',      'id="sgStatWaspada"'],
      ['fa-check-circle',         'Sinyal Bagus',   $counts['bagus'],         'var(--green)', '−25 s/d −8 dBm',       ''],
      ['fa-database',             'Total ONU',      $total_all,               'var(--ink2)',  $persen.'% bermasalah',  ''],
  ];
  foreach ($stats as [$ic, $lbl, $val, $col, $sub, $attr]): ?>
  <div class="sg-stat" <?= $attr ?>>
    <div class="k" style="color:var(--muted)"><i class="fas <?= $ic ?>" style="color:<?= $col ?>"></i><?= $lbl ?></div>
    <div class="v" style="color:<?= $col ?>"><?= (int)$val ?></div>
    <div class="s"><?= $sub ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($total === 0): ?>
<!-- Semua sinyal aman -->
<div class="sg-allgood">
  <div class="sg-allgood-icon"><i class="fas fa-signal"></i></div>
  <div style="font-size:19px;font-weight:800;color:var(--navy)">Semua Sinyal Aman</div>
  <p style="color:var(--ink2);font-size:14px;margin:8px auto 0;max-width:50ch">
    Tidak ada ONU dengan sinyal kritis atau waspada saat ini.
    Seluruh <?= $total_all ?> ONU bersinyal dalam batas normal.
  </p>
</div>

<?php else: ?>

<!-- Toolbar filter -->
<div class="sg-toolbar">
  <div class="sg-search">
    <i class="fas fa-search"></i>
    <input type="text" id="sgSearch" placeholder="Cari nama, PPPoE, area, atau model…" autocomplete="off">
  </div>
  <select class="sg-sel" id="sgAreaSel">
    <option value="">Semua area</option>
    <?php foreach (array_keys($areas) as $a): ?>
    <option value="<?= clean($a) ?>"><?= clean($a) ?> (<?= count(array_filter($rows, fn($r) => $r['area'] === $a)) ?>)</option>
    <?php endforeach; ?>
  </select>
  <button class="sg-filter-btn on" data-kat="all" id="fAll">Kritis + Waspada</button>
  <button class="sg-filter-btn" data-kat="kritis" id="fKritis">
    <i class="fas fa-circle" style="color:var(--red);font-size:8px;margin-right:4px"></i>Kritis (<?= $counts['kritis'] ?>)
  </button>
  <button class="sg-filter-btn" data-kat="waspada" id="fWaspada">
    <i class="fas fa-circle" style="color:var(--amber);font-size:8px;margin-right:4px"></i>Waspada (<?= $counts['waspada'] ?>)
  </button>
  <span class="sg-count" id="sgCount"><b><?= $total ?></b> ONU</span>
  <button class="sg-refresh" id="sgRefreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
</div>

<!-- Table -->
<div class="sg-card">
  <div class="sg-scroll">
    <table class="sg-tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Pelanggan / PPPoE</th>
          <th>Area</th>
          <th>Model</th>
          <th>RXPower (sinyal)</th>
          <th>Kategori</th>
          <th>Status ONU</th>
          <th>Last Inform</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="sgBody">
        <?php
        $stMap = [
            'online'  => ['--green', 'Online'],
            'offline' => ['--red',   'Offline'],
            'isolir'  => ['--amber', 'Isolir'],
        ];
        $katStyle = [
            'kritis'  => ['--red',   '#fee2e2', 'Kritis'],
            'waspada' => ['--amber', '#fef3c7', 'Waspada'],
        ];
        // RX bar: −40 dBm = 0%, −8 dBm = 100%.
        $rxToBar = fn(?float $rx): int => $rx === null ? 0 : max(0, min(100, (int)round(($rx - (-40)) / 32 * 100)));
        $barColor = ['kritis' => '#dc2626', 'waspada' => '#f59e0b'];

        $no = 0;
        foreach ($rows as $r):
            $no++;
            [$stCol, $stTxt]    = $stMap[$r['status']] ?? ['--muted', '—'];
            [$kCol, $kBg, $kLbl] = $katStyle[$r['kategori']];
            $barW   = $rxToBar($r['rx']);
            $bClr   = $barColor[$r['kategori']];
            $href   = $ONU_URL . 'form.php?dev=' . urlencode($r['device_id']);
            $searchKey = strtolower($r['pelanggan'] . ' ' . $r['pppoe'] . ' ' . $r['area'] . ' ' . $r['model']);
        ?>
        <tr data-kat="<?= $r['kategori'] ?>" data-area="<?= clean($r['area']) ?>"
            data-search="<?= clean($searchKey) ?>"
            onclick="location.href='<?= $href ?>'">
          <td style="color:var(--muted);font-size:12px;font-weight:700;width:36px"><?= $no ?></td>
          <td>
            <div class="sg-name"><?= clean($r['pelanggan']) ?></div>
            <?php if ($r['pppoe']): ?>
            <div class="sg-ppp"><i class="fas fa-user-tag" style="font-size:9px"></i> <?= clean($r['pppoe']) ?></div>
            <?php endif; ?>
          </td>
          <td class="sg-area-txt"><?= clean($r['area']) ?></td>
          <td class="sg-model"><?= clean($r['model']) ?></td>
          <td>
            <div class="sg-rx-wrap">
              <div style="display:flex;align-items:baseline;gap:5px">
                <span class="sg-rx-num" style="color:var(<?= $kCol ?>)"><?= $r['rx'] !== null ? number_format($r['rx'], 1) : '—' ?></span>
                <?php if ($r['rx'] !== null): ?>
                <span style="font-size:11px;color:var(--muted);font-weight:600">dBm</span>
                <?php endif; ?>
              </div>
              <div class="sg-rx-bar-bg">
                <div class="sg-rx-bar" style="width:<?= $barW ?>%;background:<?= $bClr ?>"></div>
              </div>
            </div>
          </td>
          <td>
            <span class="sg-kbadge" style="background:<?= $kBg ?>;color:var(<?= $kCol ?>)">
              <i class="fas <?= $r['kategori'] === 'kritis' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle' ?>" style="font-size:10px"></i>
              <?= $kLbl ?>
            </span>
          </td>
          <td>
            <span class="sg-sbadge" style="background:color-mix(in srgb,var(<?= $stCol ?>) 12%,#fff);color:var(<?= $stCol ?>)">
              <span class="d" style="background:var(<?= $stCol ?>)"></span><?= $stTxt ?>
            </span>
          </td>
          <td class="sg-li"><?= $r['last_inform'] ? tgl_indo($r['last_inform'], true) : '—' ?></td>
          <td style="color:var(--muted);text-align:right"><i class="fas fa-chevron-right" style="font-size:11px"></i></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="sg-empty-row" id="sgEmpty">
      <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:10px"></i>
      Tidak ada ONU yang cocok dengan filter.
    </div>
  </div>
  <div class="sg-legend">
    <span><span class="sg-ld" style="background:#dc2626"></span> Kritis &lt; −27 dBm — risiko putus, tindak segera</span>
    <span><span class="sg-ld" style="background:#f59e0b"></span> Waspada −27 s/d −25 dBm — pantau, sinyal melemah</span>
  </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();

$body_extra = <<<'HTML'
<script>
(function(){
  var body    = document.getElementById('sgBody');
  var search  = document.getElementById('sgSearch');
  var areaSel = document.getElementById('sgAreaSel');
  var countEl = document.getElementById('sgCount');
  var emptyEl = document.getElementById('sgEmpty');
  var fBtns   = Array.prototype.slice.call(document.querySelectorAll('.sg-filter-btn'));
  var fKat    = 'all';

  function applyFilter(){
    if (!body) return;
    var q = search ? search.value.trim().toLowerCase() : '';
    var a = areaSel ? areaSel.value : '';
    var trs = Array.prototype.slice.call(body.querySelectorAll('tr'));
    var shown = 0, no = 0;
    trs.forEach(function(tr){
      var katOk  = fKat === 'all' || tr.dataset.kat === fKat;
      var areaOk = !a || tr.dataset.area === a;
      var srchOk = !q || (tr.dataset.search || '').indexOf(q) !== -1;
      var ok = katOk && areaOk && srchOk;
      tr.style.display = ok ? '' : 'none';
      if (ok) {
        shown++; no++;
        var c = tr.querySelector('td:first-child');
        if (c) c.textContent = no;
      }
    });
    if (countEl) countEl.innerHTML = '<b>' + shown + '</b> ONU';
    if (emptyEl) emptyEl.style.display = shown === 0 ? 'block' : 'none';
  }

  fBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      fKat = btn.dataset.kat;
      fBtns.forEach(function(b){ b.classList.toggle('on', b === btn); });
      applyFilter();
    });
  });

  if (search)  search.addEventListener('input',  applyFilter);
  if (areaSel) areaSel.addEventListener('change', applyFilter);

  /* ── Live refresh ── */
  var refreshBtn = document.getElementById('sgRefreshBtn');
  var syncTs     = document.getElementById('sgSyncTs');
  var updated    = document.getElementById('sgUpdated');

  function doRefresh(){
    if (refreshBtn) refreshBtn.classList.add('spin');
    fetch('ajax.php', {headers:{'X-Requested-With':'fetch'}})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || !d.ok) return;
        if (syncTs && d.ref_ts) syncTs.textContent = d.ref_ts;
        if (d.counts) {
          var kEl = document.getElementById('sgStatKritis');
          var wEl = document.getElementById('sgStatWaspada');
          if (kEl && d.counts.kritis != null) kEl.querySelector('.v').textContent = d.counts.kritis;
          if (wEl && d.counts.waspada != null) wEl.querySelector('.v').textContent = d.counts.waspada;
        }
        if (updated){
          var now = new Date();
          var p = function(n){ return String(n).padStart(2,'0'); };
          updated.textContent = '· diperbarui ' + p(now.getHours())+':'+p(now.getMinutes())+':'+p(now.getSeconds());
        }
      })
      .catch(function(){})
      .finally(function(){ if (refreshBtn) refreshBtn.classList.remove('spin'); });
  }

  if (refreshBtn) refreshBtn.addEventListener('click', doRefresh);
  setInterval(doRefresh, 30000);
})();
</script>
HTML;

require __DIR__ . '/../../layout.php';
