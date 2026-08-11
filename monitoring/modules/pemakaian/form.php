<?php
// ============================================================
//  MONITORING · Modul Pemakaian — Top Pemakaian Bandwidth
//  Ringkasan + breakdown per area + peringkat pemakai (per bulan).
// ============================================================
require_once __DIR__ . '/../../_data.php';
$active   = 'pemakaian';
$MOD_URL  = MONITORING_URL . 'modules/pemakaian/';

$bulan = isset($_GET['bulan']) ? preg_replace('/[^0-9\-]/', '', (string)$_GET['bulan']) : '';
$data  = mon_pemakaian_data($bulan);

$bulan      = $data['bulan'];
$bulanList  = $data['bulan_list'];
$rows       = $data['rows'];
$areas      = $data['areas'];
$totalBytes = $data['total_bytes'];
$avgBytes   = $data['avg_bytes'];
$count      = $data['count'];
$page_title = 'Pemakaian Bandwidth';

// Label bulan Indonesia (Y-m → "Jul 2026").
function pk_bulan_label(string $ym): string {
    $b = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    [$y, $m] = array_pad(explode('-', $ym), 2, '');
    $mi = (int)$m;
    return ($mi >= 1 && $mi <= 12 ? $b[$mi] : $m) . ' ' . $y;
}

// Durasi online kasar (detik → "3hr 4j").
function pk_fmt_uptime(?int $s): string {
    if (!$s || $s <= 0) return '—';
    $h = (int)floor($s / 3600);
    $d = (int)floor($h / 24);
    if ($d > 0) return $d . 'hr ' . ($h % 24) . 'j';
    if ($h > 0) return $h . 'j ' . (int)floor(($s % 3600) / 60) . 'mnt';
    return (int)floor($s / 60) . 'mnt';
}

$top          = $rows[0] ?? null;
$maxAreaBytes = $areas ? max(array_column($areas, 'bytes')) : 0;
$topBytes     = $top ? (int)$top['bytes_out'] : 0;

// Waktu polling terakhir (untuk sync bar).
$lastPoll = null;
foreach ($rows as $r) {
    if ($r['last_poll'] && (!$lastPoll || $r['last_poll'] > $lastPoll)) $lastPoll = $r['last_poll'];
}

// Medali peringkat.
$medal = [1 => ['#b45309', '#fef3c7'], 2 => ['#64748b', '#f1f5f9'], 3 => ['#9a3412', '#ffedd5']];

ob_start();
?>
<style>
  /* ── Sync bar ── */
  .pk-syncbar{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);margin-bottom:18px;font-weight:600;flex-wrap:wrap}
  .pk-syncbar b{color:var(--ink2)}
  .pk-ldot{width:7px;height:7px;border-radius:50%;background:#22c55e;animation:mp 2s infinite;display:inline-block}
  @keyframes mp{0%{box-shadow:0 0 0 0 rgba(34,197,94,.5)}70%{box-shadow:0 0 0 5px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
  @media(prefers-reduced-motion:reduce){.pk-ldot{animation:none}}
  .pk-month{margin-left:auto;padding:8px 12px;border:1px solid var(--line);border-radius:10px;font-size:13px;
            font-family:inherit;font-weight:700;color:var(--navy);background:#fff;cursor:pointer}
  .pk-month:focus{outline:none;border-color:var(--amber)}

  /* ── Summary tiles ── */
  .pk-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:22px}
  .pk-tile{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px 20px;box-shadow:var(--shadow)}
  .pk-tile .lbl{font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:7px}
  .pk-tile .val{font-size:26px;font-weight:800;line-height:1.05;margin-top:8px;font-variant-numeric:tabular-nums}
  .pk-tile .sub{font-size:12px;color:var(--muted);margin-top:5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

  /* ── Cards ── */
  .pk-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px}
  .pk-card h3{margin:0;padding:15px 18px;font-size:13.5px;font-weight:800;color:var(--navy);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:8px}
  .pk-card h3 .hint{margin-left:auto;font-size:11.5px;font-weight:600;color:var(--muted)}

  /* ── Area breakdown ── */
  .pk-area{padding:13px 18px;border-bottom:1px solid var(--line)}
  .pk-area:last-child{border-bottom:none}
  .pk-area-top{display:flex;align-items:center;gap:10px;margin-bottom:7px}
  .pk-area-name{font-weight:700;color:var(--ink);font-size:13.5px}
  .pk-area-cnt{font-size:11.5px;color:var(--muted);font-weight:600}
  .pk-area-val{margin-left:auto;font-weight:800;color:var(--navy);font-variant-numeric:tabular-nums;font-size:13.5px}
  .pk-bar-bg{height:9px;border-radius:999px;background:#eef1f7;overflow:hidden}
  .pk-bar{height:9px;border-radius:999px;background:linear-gradient(90deg,#0ea5e9,#38bdf8);transition:width .4s}

  /* ── Toolbar ── */
  .pk-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
  .pk-search{flex:1;min-width:220px;position:relative}
  .pk-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none}
  .pk-search input{width:100%;padding:9px 13px 9px 36px;border:1px solid var(--line);border-radius:10px;
                   font-size:13.5px;font-family:inherit;color:var(--ink);background:#f8fafc;transition:.15s}
  .pk-search input:focus{outline:none;border-color:var(--amber);background:#fff;box-shadow:0 0 0 3px rgba(245,158,11,.1)}
  .pk-sel{padding:9px 13px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-family:inherit;
          color:var(--ink2);background:#f8fafc;font-weight:600;cursor:pointer}
  .pk-sel:focus{outline:none;border-color:var(--amber)}
  .pk-count{font-size:12.5px;color:var(--muted);font-weight:600;white-space:nowrap;margin-left:auto}
  .pk-count b{color:var(--ink)}

  /* ── Table ── */
  .pk-scroll{overflow-x:auto}
  table.pk-tbl{width:100%;border-collapse:collapse;font-size:13.5px}
  table.pk-tbl th{text-align:left;padding:11px 16px;font-size:10.5px;font-weight:700;color:var(--muted);
                  text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;background:#fbfcfe;border-bottom:1px solid var(--line)}
  table.pk-tbl td{padding:11px 16px;border-bottom:1px solid var(--line);vertical-align:middle}
  table.pk-tbl tbody tr:last-child td{border-bottom:none}
  table.pk-tbl tbody tr:hover{background:#f5f8fc}
  .pk-rank{width:28px;height:28px;border-radius:9px;display:flex;align-items:center;justify-content:center;
           font-weight:800;font-size:13px;color:var(--muted);background:#f1f5f9}
  .pk-name{font-weight:700;color:var(--ink)}
  .pk-area-txt{color:var(--ink2);font-weight:600}
  .pk-use-wrap{display:flex;flex-direction:column;gap:4px;min-width:150px}
  .pk-use-num{font-weight:800;font-variant-numeric:tabular-nums;color:var(--navy)}
  .pk-use-bar-bg{height:5px;border-radius:999px;background:#eef1f7;width:130px;overflow:hidden}
  .pk-use-bar{height:5px;border-radius:999px;background:linear-gradient(90deg,#0ea5e9,#38bdf8)}
  .pk-up{color:var(--ink2);font-variant-numeric:tabular-nums;font-size:12.5px}
  .pk-poll{color:var(--muted);font-size:12px;white-space:nowrap}
  .pk-empty-row{padding:40px;text-align:center;color:var(--muted);font-size:14px;display:none}

  /* ── Empty state ── */
  .pk-empty{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:60px 24px;text-align:center}
  .pk-empty-icon{width:64px;height:64px;border-radius:18px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;
                 font-size:26px;background:#eff6ff;color:#0ea5e9}
  @media(max-width:600px){.pk-tiles{grid-template-columns:1fr 1fr}}
</style>

<!-- Sync bar + pilih bulan -->
<div class="pk-syncbar">
  <span class="pk-ldot"></span>
  Pemakaian PPPoE ·
  <b id="pkSyncTs"><?= $lastPoll ? tgl_indo($lastPoll, true) : '—' ?></b>
  <span id="pkUpdated"></span>
  <?php if (!empty($bulanList)): ?>
  <select class="pk-month" onchange="location.href='form.php?bulan=' + encodeURIComponent(this.value)">
    <?php foreach ($bulanList as $bl): ?>
      <option value="<?= clean($bl) ?>" <?= $bl === $bulan ? 'selected' : '' ?>><?= pk_bulan_label($bl) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
</div>

<?php if ($count === 0): ?>
<!-- Belum ada data -->
<div class="pk-empty">
  <div class="pk-empty-icon"><i class="fas fa-tachometer-alt"></i></div>
  <div style="font-size:19px;font-weight:800;color:var(--navy)">Belum ada data pemakaian</div>
  <p style="color:var(--ink2);font-size:14px;margin:8px auto 0;max-width:52ch">
    Belum ada data <code>usage_pppoe</code> untuk bulan ini. Data akan terisi otomatis saat <b>usage:poll</b> berjalan.
  </p>
</div>

<?php else: ?>
<!-- Summary Tiles -->
<div class="pk-tiles">
  <div class="pk-tile">
    <div class="lbl"><i class="fas fa-database" style="color:#0ea5e9"></i> Total Pemakaian</div>
    <div class="val" style="color:#0ea5e9"><?= mon_fmt_bytes($totalBytes) ?></div>
    <div class="sub">Bulan <?= pk_bulan_label($bulan) ?></div>
  </div>
  <div class="pk-tile">
    <div class="lbl"><i class="fas fa-chart-line" style="color:var(--amber)"></i> Rata-rata / Pelanggan</div>
    <div class="val" style="color:var(--amber)"><?= mon_fmt_bytes($avgBytes) ?></div>
    <div class="sub">Dari <?= $count ?> pelanggan</div>
  </div>
  <div class="pk-tile">
    <div class="lbl"><i class="fas fa-users" style="color:var(--ink2)"></i> Pelanggan Aktif</div>
    <div class="val" style="color:var(--ink)"><?= $count ?></div>
    <div class="sub">Punya data pemakaian</div>
  </div>
  <div class="pk-tile">
    <div class="lbl"><i class="fas fa-crown" style="color:#b45309"></i> Pemakai Tertinggi</div>
    <div class="val" style="color:var(--navy);font-size:19px"><?= $top ? clean($top['pelanggan']) : '—' ?></div>
    <div class="sub"><?= $top ? mon_fmt_bytes($top['bytes_out']) . ' · ' . clean($top['area']) : '—' ?></div>
  </div>
</div>

<!-- Breakdown per Area -->
<div class="pk-card">
  <h3><i class="fas fa-map-marked-alt" style="color:var(--muted)"></i> Pemakaian per Area <span class="hint"><?= count($areas) ?> area</span></h3>
  <?php foreach ($areas as $a):
      $pct = $maxAreaBytes > 0 ? round($a['bytes'] / $maxAreaBytes * 100) : 0;
  ?>
  <div class="pk-area">
    <div class="pk-area-top">
      <span class="pk-area-name"><?= clean($a['area']) ?></span>
      <span class="pk-area-cnt"><i class="fas fa-user" style="font-size:9px"></i> <?= $a['count'] ?> plgn</span>
      <span class="pk-area-val"><?= mon_fmt_bytes($a['bytes']) ?></span>
    </div>
    <div class="pk-bar-bg"><div class="pk-bar" style="width:<?= $pct ?>%"></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Peringkat Pemakai -->
<div class="pk-card">
  <h3><i class="fas fa-trophy" style="color:var(--muted)"></i> Peringkat Pemakai</h3>
  <div class="pk-toolbar" style="padding:14px 16px;margin:0;border-bottom:1px solid var(--line)">
    <div class="pk-search">
      <i class="fas fa-search"></i>
      <input type="text" id="pkSearch" placeholder="Cari nama pelanggan atau area…" autocomplete="off">
    </div>
    <select class="pk-sel" id="pkAreaSel">
      <option value="">Semua area</option>
      <?php foreach ($areas as $a): ?>
        <option value="<?= clean($a['area']) ?>"><?= clean($a['area']) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="pk-count" id="pkCount"><b><?= $count ?></b> pelanggan</span>
  </div>
  <div class="pk-scroll">
    <table class="pk-tbl">
      <thead>
        <tr>
          <th style="width:52px">#</th>
          <th>Pelanggan</th>
          <th>Area</th>
          <th>Pemakaian</th>
          <th>Durasi Online</th>
          <th>Update Terakhir</th>
        </tr>
      </thead>
      <tbody id="pkBody">
        <?php foreach ($rows as $r):
            $pct = $topBytes > 0 ? round((int)$r['bytes_out'] / $topBytes * 100) : 0;
            [$mc, $mbg] = $medal[$r['rank']] ?? [null, null];
            $searchKey = strtolower($r['pelanggan'] . ' ' . $r['area']);
        ?>
        <tr data-area="<?= clean($r['area']) ?>" data-search="<?= clean($searchKey) ?>">
          <td>
            <span class="pk-rank" <?= $mc ? 'style="color:' . $mc . ';background:' . $mbg . '"' : '' ?>><?= $r['rank'] ?></span>
          </td>
          <td class="pk-name"><?= clean($r['pelanggan']) ?></td>
          <td class="pk-area-txt"><?= clean($r['area']) ?></td>
          <td>
            <div class="pk-use-wrap">
              <span class="pk-use-num"><?= mon_fmt_bytes((int)$r['bytes_out']) ?></span>
              <div class="pk-use-bar-bg"><div class="pk-use-bar" style="width:<?= $pct ?>%"></div></div>
            </div>
          </td>
          <td class="pk-up"><?= pk_fmt_uptime((int)$r['uptime_sec']) ?></td>
          <td class="pk-poll"><?= $r['last_poll'] ? tgl_indo($r['last_poll'], true) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="pk-empty-row" id="pkNoResult"><i class="fas fa-inbox" style="font-size:22px;display:block;margin-bottom:8px"></i>Tidak ada pelanggan yang cocok dengan filter.</div>
  </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();

$body_extra = <<<'HTML'
<script>
(function(){
  var search  = document.getElementById('pkSearch');
  var areaSel = document.getElementById('pkAreaSel');
  var count   = document.getElementById('pkCount');
  var noRes   = document.getElementById('pkNoResult');
  var body    = document.getElementById('pkBody');

  if (search && body) {
    var rows = Array.prototype.slice.call(body.querySelectorAll('tr'));
    function applyFilter(){
      var q = search.value.trim().toLowerCase();
      var a = areaSel ? areaSel.value : '';
      var shown = 0;
      rows.forEach(function(tr){
        var ok = (!a || tr.dataset.area === a) && (!q || (tr.dataset.search || '').indexOf(q) !== -1);
        tr.style.display = ok ? '' : 'none';
        if (ok) shown++;
      });
      count.innerHTML = '<b>' + shown + '</b> pelanggan';
      if (noRes) noRes.style.display = shown === 0 ? 'block' : 'none';
    }
    search.addEventListener('input', applyFilter);
    if (areaSel) areaSel.addEventListener('change', applyFilter);
  }
})();
</script>
HTML;

require __DIR__ . '/../../layout.php';
