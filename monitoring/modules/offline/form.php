<?php
// ============================================================
//  MONITORING · Modul Offline — ONU Offline per Area
//  Menampilkan ONU yang sedang offline, dikelompokkan per area
//  untuk memudahkan deteksi gangguan cluster (listrik/kabel).
// ============================================================
require_once __DIR__ . '/../../_data.php';
$page_title = 'ONU Offline';
$active     = 'offline';
$MOD_URL    = MONITORING_URL . 'modules/offline/';
$ONU_URL    = MONITORING_URL . 'modules/onu/';

$data      = mon_offline_by_area();
$total     = $data['total'];
$areas     = $data['areas'];
$ref       = $data['ref'];
$areaCount = count($areas);

// Cari area paling parah (ONU offline terbanyak).
$worstArea = $areaCount > 0 ? array_key_first($areas) : null;
$worstCount = $worstArea ? count($areas[$worstArea]) : 0;

// Durasi terlama offline dari seluruh ONU.
$maxDurasi = 0;
foreach ($areas as $list) {
    foreach ($list as $onu) {
        if (($onu['durasi_detik'] ?? 0) > $maxDurasi) $maxDurasi = $onu['durasi_detik'];
    }
}

// Helper: format durasi detik → string manusiawi.
function fmt_durasi(?int $sek): string {
    if ($sek === null || $sek <= 0) return '—';
    if ($sek < 60)   return $sek . 'd';
    if ($sek < 3600) return floor($sek / 60) . 'mnt';
    if ($sek < 86400) return floor($sek / 3600) . 'j ' . floor(($sek % 3600) / 60) . 'mnt';
    return floor($sek / 86400) . 'hr ' . floor(($sek % 86400) / 3600) . 'j';
}

// Tingkat keparahan area berdasarkan jumlah ONU offline.
function area_severity(int $n): array {
    if ($n >= 5) return ['--red',   '#fef2f2', '#fee2e2', 'Kritis'];
    if ($n >= 3) return ['--warn',  '#fffbeb', '#fef3c7', 'Parah'];
    if ($n >= 2) return ['--amber', '#fffbeb', '#fef3c7', 'Sedang'];
    return ['--ink2', '#f8fafc', '#f1f5f9', 'Ringan'];
}

ob_start();
?>
<style>
  /* ── Summary tiles ── */
  .of-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px}
  .of-tile{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px 20px;box-shadow:var(--shadow);position:relative;overflow:hidden}
  .of-tile::before{content:'';position:absolute;inset:0;opacity:.04;border-radius:16px}
  .of-tile .lbl{font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:7px}
  .of-tile .val{font-size:30px;font-weight:800;line-height:1;margin-top:7px}
  .of-tile .sub{font-size:12px;color:var(--muted);margin-top:4px;font-weight:600}

  /* ── Toolbar ── */
  .of-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
  .of-search{flex:1;min-width:220px;position:relative}
  .of-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none}
  .of-search input{width:100%;padding:9px 13px 9px 36px;border:1px solid var(--line);border-radius:10px;font-size:13.5px;font-family:inherit;color:var(--ink);background:#f8fafc;transition:.15s}
  .of-search input:focus{outline:none;border-color:var(--amber);background:#fff;box-shadow:0 0 0 3px rgba(245,158,11,.1)}
  .of-sel{padding:9px 13px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-family:inherit;color:var(--ink2);background:#f8fafc;font-weight:600;cursor:pointer}
  .of-sel:focus{outline:none;border-color:var(--amber)}
  .of-count{font-size:12.5px;color:var(--muted);font-weight:600;white-space:nowrap;margin-left:auto}
  .of-count b{color:var(--ink)}
  .of-refresh{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border-radius:10px;font-size:13px;font-weight:700;
              background:var(--navy);color:#fff;border:none;cursor:pointer;font-family:inherit;transition:.15s}
  .of-refresh:hover{background:#173257}
  .of-refresh.spin i{animation:spin .7s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}

  /* ── Area cards ── */
  .of-area{border-radius:16px;border:1px solid var(--line);box-shadow:var(--shadow);overflow:hidden;margin-bottom:14px;transition:.2s}
  .of-area-head{display:flex;align-items:center;gap:12px;padding:14px 18px;cursor:pointer;user-select:none;transition:background .12s}
  .of-area-head:hover{filter:brightness(.97)}
  .of-area-icon{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
  .of-area-name{font-size:15px;font-weight:800;color:var(--navy)}
  .of-area-sev{font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:3px 10px;border-radius:999px;margin-left:8px}
  .of-area-count{margin-left:auto;font-size:13px;font-weight:700;color:var(--muted)}
  .of-chev{color:var(--muted);font-size:11px;margin-left:10px;transition:transform .2s}
  .of-area.open .of-chev{transform:rotate(90deg)}
  .of-area-body{display:none;border-top:1px solid var(--line)}
  .of-area.open .of-area-body{display:block}

  /* ── ONU table ── */
  .of-scroll{overflow-x:auto}
  table.of-tbl{width:100%;border-collapse:collapse;font-size:13.5px}
  table.of-tbl th{text-align:left;padding:10px 16px;font-size:10.5px;font-weight:700;color:var(--muted);
                  text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;background:#fbfcfe;border-bottom:1px solid var(--line)}
  table.of-tbl td{padding:11px 16px;border-bottom:1px solid var(--line);vertical-align:middle}
  table.of-tbl tbody tr:last-child td{border-bottom:none}
  table.of-tbl tbody tr{cursor:pointer;transition:background .1s}
  table.of-tbl tbody tr:hover{background:#f5f8fc}
  .of-name{font-weight:700;color:var(--ink)}
  .of-ppp{font-size:11.5px;color:var(--muted);margin-top:1px}
  .of-model{color:var(--ink2);font-weight:600}
  .of-dur{font-weight:700;font-variant-numeric:tabular-nums;color:var(--red)}
  .of-dur.warn{color:var(--warn)}
  .of-dur.ok{color:var(--ink2)}
  .of-rx{font-weight:700;font-variant-numeric:tabular-nums}
  .of-rx small{font-weight:600;color:var(--muted);font-size:10.5px;margin-left:2px}
  .of-li{color:var(--ink2);font-size:12.5px;white-space:nowrap}
  .of-hp{color:var(--ink2);font-size:12.5px}

  /* ── Empty state ── */
  .of-empty{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);
            padding:60px 24px;text-align:center}
  .of-empty-icon{width:64px;height:64px;border-radius:18px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;
                 font-size:26px;background:linear-gradient(135deg,#dcfce7,#bbf7d0);color:#16a34a}
  .of-badge-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px;vertical-align:middle}

  /* ── Sync badge ── */
  .of-syncbar{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);margin-bottom:16px;font-weight:600}
  .of-syncbar b{color:var(--ink2)}
  .of-live-dot{width:7px;height:7px;border-radius:50%;background:#22c55e;animation:mp 2s infinite;display:inline-block}
  @keyframes mp{0%{box-shadow:0 0 0 0 rgba(34,197,94,.5)}70%{box-shadow:0 0 0 5px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
  @media(prefers-reduced-motion:reduce){.of-live-dot{animation:none}}
  @media(max-width:600px){.of-tiles{grid-template-columns:1fr 1fr}}
</style>

<!-- Sync bar -->
<div class="of-syncbar">
  <span class="of-live-dot"></span>
  Data cache GenieACS ·
  <b id="ofSyncTs"><?= $ref ? date('d M Y, H:i:s', $ref) : '—' ?></b>
  <span id="ofUpdated" style="margin-left:4px"></span>
</div>

<!-- Summary Tiles -->
<div class="of-tiles">
  <?php
  $tiles = [
      ['fa-wifi',                'ONU Offline',     $total,      'var(--red)',   $total > 0 ? 'Perlu ditindaklanjuti' : 'Semua online'],
      ['fa-map-marked-alt',      'Area Terdampak',  $areaCount,  'var(--warn)',  $areaCount > 0 ? ($worstArea . ' (' . $worstCount . ' ONU)') : 'Tidak ada'],
      ['fa-exclamation-triangle','Terlama Offline',  $maxDurasi > 0 ? fmt_durasi($maxDurasi) : '—', 'var(--red)', $maxDurasi > 0 ? 'Perlu prioritas' : 'Semua normal'],
  ];
  foreach ($tiles as [$ic, $lbl, $val, $col, $sub]): ?>
  <div class="of-tile">
    <div class="lbl"><i class="fas <?= $ic ?>"></i><?= $lbl ?></div>
    <div class="val" style="color:<?= $col ?>"><?= is_int($val) ? $val : clean($val) ?></div>
    <div class="sub"><?= clean($sub) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($total === 0): ?>
<!-- Semua ONU Online -->
<div class="of-empty">
  <div class="of-empty-icon"><i class="fas fa-check-circle"></i></div>
  <div style="font-size:19px;font-weight:800;color:var(--navy)">Semua ONU Online</div>
  <p style="color:var(--ink2);font-size:14px;margin:8px auto 0;max-width:50ch">
    Tidak ada ONU yang sedang offline saat ini. Data berdasarkan snapshot cache GenieACS terakhir.
  </p>
</div>

<?php else: ?>
<!-- Toolbar -->
<div class="of-toolbar">
  <div class="of-search">
    <i class="fas fa-search"></i>
    <input type="text" id="ofSearch" placeholder="Cari nama pelanggan, PPPoE, atau model…" autocomplete="off">
  </div>
  <select class="of-sel" id="ofAreaSel">
    <option value="">Semua area (<?= $areaCount ?>)</option>
    <?php foreach (array_keys($areas) as $a): ?>
    <option value="<?= clean($a) ?>"><?= clean($a) ?> (<?= count($areas[$a]) ?>)</option>
    <?php endforeach; ?>
  </select>
  <span class="of-count" id="ofCount"><b><?= $total ?></b> ONU offline</span>
  <button class="of-refresh" id="ofRefreshBtn" title="Perbarui data">
    <i class="fas fa-sync-alt"></i> Refresh
  </button>
</div>

<!-- Area Cards -->
<div id="ofAreaList">
<?php foreach ($areas as $areaName => $onuList):
    [$sevColor, $sevBg, $iconBg, $sevLabel] = area_severity(count($onuList));
    $areaId = 'area-' . preg_replace('/[^a-z0-9]/i', '-', $areaName);
?>
<div class="of-area open" data-area="<?= clean($areaName) ?>" id="<?= $areaId ?>">
  <div class="of-area-head" style="background:<?= $sevBg ?>" onclick="toggleArea('<?= $areaId ?>')">
    <div class="of-area-icon" style="background:<?= $iconBg ?>;color:var(<?= $sevColor ?>)">
      <i class="fas fa-map-marker-alt"></i>
    </div>
    <div>
      <div class="of-area-name"><?= clean($areaName) ?></div>
      <div style="font-size:11.5px;color:var(<?= $sevColor ?>);font-weight:600;margin-top:2px">
        <?= count($onuList) ?> ONU offline
      </div>
    </div>
    <span class="of-area-sev" style="background:color-mix(in srgb,var(<?= $sevColor ?>) 14%,#fff);color:var(<?= $sevColor ?>)">
      <?= $sevLabel ?>
    </span>
    <span class="of-area-count">
      <?php
      $maxD = max(array_column($onuList, 'durasi_detik') ?: [0]);
      if ($maxD > 0) echo '<i class="fas fa-clock" style="font-size:11px;margin-right:4px"></i>' . fmt_durasi($maxD) . ' terlama';
      ?>
    </span>
    <i class="fas fa-chevron-right of-chev"></i>
  </div>

  <div class="of-area-body">
    <div class="of-scroll">
      <table class="of-tbl">
        <thead>
          <tr>
            <th>Pelanggan</th>
            <th>Model ONU</th>
            <th>RXPower</th>
            <th>Durasi Offline</th>
            <th>Last Inform</th>
            <th>No. HP</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($onuList as $onu):
              [$rxCol, $rxTxt] = mon_rx_style($onu['rx']);
              $dur = $onu['durasi_detik'];
              $durClass = ($dur !== null && $dur > 7200) ? 'of-dur' : (($dur !== null && $dur > 1800) ? 'of-dur warn' : 'of-dur ok');
              $href = $ONU_URL . 'form.php?dev=' . urlencode($onu['device_id']);
              $searchKey = strtolower($onu['pelanggan'] . ' ' . $onu['pppoe'] . ' ' . $onu['model']);
          ?>
          <tr data-search="<?= clean($searchKey) ?>" onclick="location.href='<?= $href ?>'">
            <td>
              <div class="of-name"><?= clean($onu['pelanggan']) ?></div>
              <?php if ($onu['pppoe']): ?>
              <div class="of-ppp"><i class="fas fa-user-tag" style="font-size:9px"></i> <?= clean($onu['pppoe']) ?></div>
              <?php endif; ?>
            </td>
            <td class="of-model"><?= clean($onu['model']) ?></td>
            <td>
              <span class="of-rx" style="color:var(<?= $rxCol ?>)">
                <?= $rxTxt ?><?php if ($onu['rx'] !== null): ?><small>dBm</small><?php endif; ?>
              </span>
            </td>
            <td>
              <span class="<?= $durClass ?>">
                <?php if ($dur !== null): ?>
                  <i class="fas fa-clock" style="font-size:10px;margin-right:3px"></i><?= fmt_durasi($dur) ?>
                <?php else: ?>—<?php endif; ?>
              </span>
            </td>
            <td class="of-li"><?= $onu['last_inform'] ? tgl_indo($onu['last_inform'], true) : '—' ?></td>
            <td class="of-hp"><?= $onu['no_hp'] ? clean($onu['no_hp']) : '—' ?></td>
            <td style="color:var(--muted);text-align:right"><i class="fas fa-chevron-right" style="font-size:11px"></i></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- No result setelah filter -->
<div id="ofNoResult" style="display:none;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:40px;text-align:center;color:var(--muted);font-size:14px">
  <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:10px"></i>
  Tidak ada ONU offline yang cocok dengan filter.
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();

$body_extra = <<<'HTML'
<script>
(function(){
  /* ── Toggle collapse area card ── */
  window.toggleArea = function(id){
    var el = document.getElementById(id);
    if (el) el.classList.toggle('open');
  };

  /* ── Client-side filter (search + area) ── */
  var search  = document.getElementById('ofSearch');
  var areaSel = document.getElementById('ofAreaSel');
  var count   = document.getElementById('ofCount');
  var noRes   = document.getElementById('ofNoResult');

  if (search) {
    function applyFilter(){
      var q = search.value.trim().toLowerCase();
      var a = areaSel ? areaSel.value : '';
      var shown = 0;
      var areaCards = document.querySelectorAll('.of-area');
      areaCards.forEach(function(card){
        var cardArea = card.dataset.area || '';
        var areaMatch = !a || cardArea === a;
        if (!areaMatch){ card.style.display='none'; return; }

        var rows = card.querySelectorAll('tbody tr');
        var visRows = 0;
        rows.forEach(function(tr){
          var sk = tr.dataset.search || '';
          var ok = !q || sk.indexOf(q) !== -1;
          tr.style.display = ok ? '' : 'none';
          if (ok) { shown++; visRows++; }
        });
        card.style.display = visRows > 0 ? '' : 'none';
      });
      if (count) count.innerHTML = '<b>' + shown + '</b> ONU offline';
      if (noRes) noRes.style.display = shown === 0 && (q || a) ? 'block' : 'none';
    }
    search.addEventListener('input', applyFilter);
    if (areaSel) areaSel.addEventListener('change', applyFilter);
  }

  /* ── Live poll tiap 30 detik ── */
  var refreshBtn = document.getElementById('ofRefreshBtn');
  var syncTs     = document.getElementById('ofSyncTs');
  var updated    = document.getElementById('ofUpdated');

  function doRefresh(){
    if (refreshBtn) refreshBtn.classList.add('spin');
    fetch('ajax.php', {headers:{'X-Requested-With':'fetch'}})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || !d.ok) return;
        // Update summary counts
        var tileVals = document.querySelectorAll('.of-tile .val');
        if (tileVals[0] && d.total != null) tileVals[0].textContent = d.total;
        if (tileVals[1] && d.area_count != null) tileVals[1].textContent = d.area_count;
        // Update timestamp
        if (syncTs && d.ref_ts) syncTs.textContent = d.ref_ts;
        if (updated) {
          var now = new Date();
          var p = function(n){ return String(n).padStart(2,'0'); };
          updated.textContent = '· diperbarui ' + p(now.getHours()) + ':' + p(now.getMinutes()) + ':' + p(now.getSeconds());
        }
      })
      .catch(function(){})
      .finally(function(){
        if (refreshBtn) refreshBtn.classList.remove('spin');
      });
  }

  if (refreshBtn) refreshBtn.addEventListener('click', doRefresh);
  setInterval(doRefresh, 30000);
})();
</script>
HTML;

require __DIR__ . '/../../layout.php';
