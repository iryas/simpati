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
$totalBytes   = $data['total_bytes'];
$avgBytes     = $data['avg_bytes'];
$avgBytesHari = $data['avg_bytes_hari'];
$hariTotal    = $data['hari_total'];
$hariBerjalan = $data['hari_berjalan'];
$count      = $data['count'];
$page_title = 'Pemakaian Bandwidth';

// Tab aktif di card "Pemakaian per Area" / "Tren Pemakaian Harian" / "Kena FUP".
$activeTab = in_array($_GET['tab'] ?? '', ['tren', 'fup'], true) ? $_GET['tab'] : 'area';

// Rentang tanggal buat matriks Tren Pemakaian Harian (opsional dari GET,
// mon_pemakaian_matrix() yang urus default & clamp ke batas periode).
$trenAwal  = isset($_GET['tren_awal'])  ? preg_replace('/[^0-9\-]/', '', (string)$_GET['tren_awal'])  : '';
$trenAkhir = isset($_GET['tren_akhir']) ? preg_replace('/[^0-9\-]/', '', (string)$_GET['tren_akhir']) : '';
$matrix    = mon_pemakaian_matrix($bulan, $trenAwal, $trenAkhir);

// Pelanggan yang lagi kena FUP hari ini.
$fupList = mon_fup_hari_ini();

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

// Tanggal pendek buat header kolom matriks ("26 Agt", tanpa tahun).
function pk_tgl_pendek(string $ymd): string {
    $b  = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($ymd);
    return date('d', $ts) . ' ' . $b[(int)date('n', $ts)];
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

  /* ── Tab: Pemakaian per Area / Tren Pemakaian Harian ── */
  .pk-tabs{display:flex;border-bottom:1px solid var(--line);padding:0 8px}
  .pk-tab-btn{display:inline-flex;align-items:center;gap:7px;padding:14px;font-size:13.5px;font-weight:700;
              color:var(--muted);background:none;border:none;border-bottom:2px solid transparent;
              cursor:pointer;font-family:inherit;white-space:nowrap}
  .pk-tab-btn:hover{color:var(--ink2)}
  .pk-tab-btn.active{color:var(--navy);border-bottom-color:var(--amber)}
  .pk-tab-hint{padding:11px 18px;font-size:11.5px;font-weight:600;color:var(--muted)}
  .pk-fup-badge{background:var(--red);color:#fff;font-size:11px;font-weight:800;border-radius:999px;
                padding:1px 7px;line-height:1.5;margin-left:2px}

  /* ── Tren Pemakaian Harian: filter tanggal ── */
  .pk-tren-filter{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;padding:14px 18px;border-bottom:1px solid var(--line)}
  .pk-tren-filter label{display:block;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px}
  .pk-tren-filter input[type="date"]{padding:8px 10px;border:1px solid var(--line);border-radius:9px;font-size:13px;
                                      font-family:inherit;color:var(--ink);background:#f8fafc}
  .pk-tren-filter input[type="date"]:focus{outline:none;border-color:var(--amber)}
  .pk-tren-filter-btn{padding:9px 16px;border:1px solid var(--navy);border-radius:9px;font-size:13px;font-weight:700;
                       font-family:inherit;color:#fff;background:var(--navy);cursor:pointer}
  .pk-tren-filter-btn:hover{background:#173257}
  .pk-tren-empty{padding:40px 24px;text-align:center;color:var(--muted);font-size:13.5px}

  /* ── Tren Pemakaian Harian: matriks ── */
  table.pk-matrix th.pk-matrix-date{text-align:right}
  table.pk-matrix th.pk-matrix-today,table.pk-matrix td.pk-matrix-today{background:#fffbeb}
  .pk-matrix-cell{text-align:right;min-width:82px}
  .pk-matrix-bytes{font-weight:800;font-variant-numeric:tabular-nums;color:var(--navy);font-size:13px}
  .pk-matrix-bytes.pk-matrix-empty{font-weight:600;color:var(--muted)}
  .pk-matrix-dur{font-size:11px;font-weight:600;color:var(--muted);font-variant-numeric:tabular-nums;margin-top:2px}
  tr.pk-matrix-total td{background:#fbfcfe;font-weight:800;color:var(--navy);border-top:2px solid var(--line)}
  tr.pk-matrix-total .pk-matrix-bytes{color:var(--navy)}

  /* ── Kolom Aksi + tombol Detail ── */
  .pk-act{text-align:right}
  .df-actbtn{display:inline-flex;align-items:center;gap:6px;font-family:inherit;font-size:12px;font-weight:700;
             color:var(--navy);background:#fff;border:1px solid var(--line);border-radius:9px;padding:6px 12px;cursor:pointer;transition:.12s;white-space:nowrap}
  .df-actbtn:hover{border-color:var(--amber);background:#fffbeb;color:var(--warn)}

  /* ── Modal Detail Pemakaian Harian ── */
  .pd-ov{position:fixed;inset:0;background:rgba(15,39,68,.5);z-index:60;display:none;
         align-items:flex-start;justify-content:center;padding:44px 16px;overflow-y:auto}
  .pd-ov.show{display:flex}
  .pd-modal{background:var(--card);border-radius:18px;box-shadow:0 20px 60px rgba(15,39,68,.35);width:100%;max-width:520px;overflow:hidden}
  .pd-head{background:linear-gradient(120deg,#0a1e3d,#0f2744 55%,#173257);color:#fff;padding:18px 20px;display:flex;align-items:flex-start;gap:12px}
  .pd-head .ic{width:40px;height:40px;border-radius:11px;background:rgba(245,158,11,.18);color:#fbbf24;
               display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
  .pd-head h3{margin:0;font-size:16px;font-weight:800}
  .pd-head p{margin:2px 0 0;font-size:12px;color:rgba(255,255,255,.6);word-break:break-word}
  .pd-close{margin-left:auto;background:rgba(255,255,255,.12);border:0;color:#fff;width:30px;height:30px;
            border-radius:8px;cursor:pointer;font-size:14px;line-height:1;flex-shrink:0}
  .pd-close:hover{background:rgba(255,255,255,.2)}
  .pd-body{padding:20px}
  .pd-note{display:flex;gap:9px;align-items:flex-start;background:#eff6ff;color:#0ea5e9;border-radius:10px;
           padding:11px 13px;font-size:12px;font-weight:600;line-height:1.5;margin-bottom:16px}
  .pd-note b{color:#0369a1}
  .pd-loading{text-align:center;padding:30px 0;color:var(--muted);font-size:13px}
  table.pd-tbl{width:100%;border-collapse:collapse;font-size:13px}
  .pd-tbl th{text-align:left;padding:8px 4px;font-size:10px;font-weight:700;color:var(--muted);
             text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--line)}
  .pd-tbl th.num{text-align:right}
  .pd-tbl td{padding:8px 4px;border-bottom:1px solid var(--line);color:var(--ink2);font-weight:600}
  .pd-tbl tbody tr:last-child td{border-bottom:none}
  .pd-tbl td.num{text-align:right;font-variant-numeric:tabular-nums;font-weight:800;color:var(--navy)}
  .pd-tbl td.dur{text-align:right;font-variant-numeric:tabular-nums;font-weight:600;color:var(--ink2);font-size:12.5px}
  .pd-empty-row td{text-align:center;color:var(--muted);font-style:italic;font-weight:600;background:#fbfcfe}
  .pd-today td{background:#fffbeb}
  .pd-today td.num{color:var(--warn)}
  .pd-today td.dur{color:var(--warn);font-weight:700}
  .pd-foot{padding:14px 20px;border-top:1px solid var(--line);display:flex;justify-content:flex-end}
  .pd-btn{font-family:inherit;font-size:13px;font-weight:700;padding:9px 16px;border-radius:10px;cursor:pointer;
          border:1px solid var(--line);background:#fff;color:var(--ink2)}

  @media(max-width:600px){.pk-tiles{grid-template-columns:1fr 1fr}}
</style>

<!-- Sync bar + pilih bulan -->
<div class="pk-syncbar">
  <span class="pk-ldot"></span>
  Pemakaian PPPoE ·
  <b id="pkSyncTs"><?= $lastPoll ? tgl_indo($lastPoll, true) : '—' ?></b>
  <span id="pkUpdated"></span>
  <?php if (!empty($bulanList)): ?>
  <select class="pk-month" onchange="location.href='form.php?bulan=' + encodeURIComponent(this.value) + '&tab=<?= $activeTab ?>'">
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
    <div class="lbl"><i class="fas fa-calendar-day" style="color:#22c55e"></i> Rata-rata Harian / Pelanggan</div>
    <div class="val" style="color:#22c55e"><?= mon_fmt_bytes($avgBytesHari) ?></div>
    <div class="sub">Hari ke-<?= $hariBerjalan ?> dari <?= $hariTotal ?></div>
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

<!-- Pemakaian per Area / Tren Pemakaian Harian (tab) -->
<div class="pk-card">
  <div class="pk-tabs">
    <button type="button" class="pk-tab-btn <?= $activeTab === 'area' ? 'active' : '' ?>" data-tab="area" onclick="pkSwitchTab('area')">
      <i class="fas fa-map-marked-alt"></i> Pemakaian per Area
    </button>
    <button type="button" class="pk-tab-btn <?= $activeTab === 'tren' ? 'active' : '' ?>" data-tab="tren" onclick="pkSwitchTab('tren')">
      <i class="fas fa-table"></i> Tren Pemakaian Harian
    </button>
    <button type="button" class="pk-tab-btn <?= $activeTab === 'fup' ? 'active' : '' ?>" data-tab="fup" onclick="pkSwitchTab('fup')">
      <i class="fas fa-tachometer-alt"></i> Kena FUP
      <?php if (count($fupList) > 0): ?><span class="pk-fup-badge"><?= count($fupList) ?></span><?php endif; ?>
    </button>
  </div>

  <!-- Panel: Pemakaian per Area -->
  <div class="pk-tab-panel" data-panel="area" style="<?= $activeTab === 'area' ? '' : 'display:none' ?>">
    <div class="pk-tab-hint"><?= count($areas) ?> area</div>
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

  <!-- Panel: Tren Pemakaian Harian (matriks pelanggan x tanggal) -->
  <div class="pk-tab-panel" data-panel="tren" style="<?= $activeTab === 'tren' ? '' : 'display:none' ?>">
    <form method="GET" action="form.php" class="pk-tren-filter">
      <input type="hidden" name="bulan" value="<?= clean($bulan) ?>">
      <input type="hidden" name="tab" value="tren">
      <div>
        <label for="trenAwal">Dari</label>
        <input type="date" id="trenAwal" name="tren_awal"
               value="<?= clean($matrix['tgl_awal'] ?? '') ?>"
               min="<?= clean($matrix['periode_awal'] ?? '') ?>" max="<?= clean($matrix['periode_akhir'] ?? '') ?>">
      </div>
      <div>
        <label for="trenAkhir">Sampai</label>
        <input type="date" id="trenAkhir" name="tren_akhir"
               value="<?= clean($matrix['tgl_akhir'] ?? '') ?>"
               min="<?= clean($matrix['periode_awal'] ?? '') ?>" max="<?= clean($matrix['periode_akhir'] ?? '') ?>">
      </div>
      <button type="submit" class="pk-tren-filter-btn">Tampilkan</button>
    </form>

    <?php if (empty($matrix['ok']) || empty($matrix['pelanggan'])): ?>
      <div class="pk-tren-empty">
        <i class="fas fa-inbox" style="font-size:22px;display:block;margin-bottom:8px"></i>
        Belum ada data pemakaian harian buat rentang tanggal ini.
      </div>
    <?php else:
        $hariIni = date('Y-m-d');
    ?>
      <div class="pk-scroll">
        <table class="pk-tbl pk-matrix">
          <thead>
            <tr>
              <th>Pelanggan</th>
              <th>Area</th>
              <th>Paket</th>
              <?php foreach ($matrix['tanggal_list'] as $tgl): ?>
                <th class="pk-matrix-date <?= $tgl === $hariIni ? 'pk-matrix-today' : '' ?>"><?= pk_tgl_pendek($tgl) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($matrix['pelanggan'] as $p): ?>
            <tr>
              <td class="pk-name"><?= clean($p['pelanggan']) ?></td>
              <td class="pk-area-txt"><?= clean($p['area']) ?></td>
              <td class="pk-area-txt"><?= clean($p['paket']) ?></td>
              <?php foreach ($matrix['tanggal_list'] as $tgl):
                  $cell = $p['hari'][$tgl] ?? null;
                  $tdCls = $tgl === $hariIni ? 'pk-matrix-cell pk-matrix-today' : 'pk-matrix-cell';
              ?>
                <td class="<?= $tdCls ?>">
                  <?php if ($cell): ?>
                    <div class="pk-matrix-bytes"><?= mon_fmt_bytes($cell['bytes_out']) ?></div>
                    <div class="pk-matrix-dur"><?= mon_fmt_durasi($cell['uptime_seconds']) ?></div>
                  <?php else: ?>
                    <div class="pk-matrix-bytes pk-matrix-empty">—</div>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="pk-matrix-total">
              <td colspan="3">Total</td>
              <?php foreach ($matrix['tanggal_list'] as $tgl):
                  $tdCls = $tgl === $hariIni ? 'pk-matrix-cell pk-matrix-today' : 'pk-matrix-cell';
              ?>
                <td class="<?= $tdCls ?>">
                  <div class="pk-matrix-bytes"><?= mon_fmt_bytes($matrix['total_per_tanggal'][$tgl]) ?></div>
                </td>
              <?php endforeach; ?>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Panel: Kena FUP -->
  <div class="pk-tab-panel" data-panel="fup" style="<?= $activeTab === 'fup' ? '' : 'display:none' ?>">
    <?php if (empty($fupList)): ?>
      <div class="pk-tren-empty">
        <i class="fas fa-check-circle" style="font-size:22px;display:block;margin-bottom:8px;color:#22c55e"></i>
        Nggak ada pelanggan yang kena FUP hari ini.
      </div>
    <?php else: ?>
      <div class="pk-scroll">
        <table class="pk-tbl">
          <thead>
            <tr>
              <th>Pelanggan</th>
              <th>Area</th>
              <th>Paket</th>
              <th>Pemakaian Hari Ini</th>
              <th>Kena Sejak</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fupList as $f): ?>
            <tr>
              <td class="pk-name"><?= clean($f['pelanggan']) ?></td>
              <td class="pk-area-txt"><?= clean($f['area']) ?></td>
              <td class="pk-area-txt"><?= clean($f['paket']) ?></td>
              <td class="pk-use-num"><?= mon_fmt_bytes($f['bytes_out']) ?></td>
              <td class="pk-poll"><?= $f['fup_diterapkan_at'] ? tgl_indo($f['fup_diterapkan_at'], true) : '—' ?></td>
              <td class="pk-act">
                <form method="POST" action="fup_cabut.php?bulan=<?= urlencode($bulan) ?>" onsubmit="return confirm('Cabut FUP untuk <?= clean(addslashes($f['pelanggan'])) ?>? Profile normal akan dikirim & ONT-nya di-reboot.');">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="pelanggan_id" value="<?= (int)$f['pelanggan_id'] ?>">
                  <button type="submit" class="df-actbtn">
                    <i class="fas fa-undo"></i> Cabut FUP
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
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
          <th>Rata-rata/Hari</th>
          <th>Durasi Online</th>
          <th>Update Terakhir</th>
          <th>Aksi</th>
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
          <td class="pk-up"><?= mon_fmt_bytes((int)$r['bytes_per_hari']) ?>/hr</td>
          <td class="pk-up"><?= pk_fmt_uptime((int)$r['uptime_sec']) ?></td>
          <td class="pk-poll"><?= $r['last_poll'] ? tgl_indo($r['last_poll'], true) : '—' ?></td>
          <td class="pk-act">
            <button type="button" class="df-actbtn" onclick="pdOpen(<?= (int)$r['pelanggan_id'] ?>)">
              <i class="fas fa-calendar-alt"></i> Detail
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="pk-empty-row" id="pkNoResult"><i class="fas fa-inbox" style="font-size:22px;display:block;margin-bottom:8px"></i>Tidak ada pelanggan yang cocok dengan filter.</div>
  </div>
</div>
<?php endif; ?>

<!-- Modal: Detail Pemakaian Harian -->
<div class="pd-ov" id="pdOverlay" data-bulan="<?= clean($bulan) ?>">
  <div class="pd-modal">
    <div class="pd-head">
      <div class="ic"><i class="fas fa-calendar-alt"></i></div>
      <div>
        <h3>Pemakaian Harian</h3>
        <p id="pdSub">—</p>
      </div>
      <button type="button" class="pd-close" onclick="pdClose()" aria-label="Tutup"><i class="fas fa-times"></i></button>
    </div>
    <div class="pd-body">
      <div class="pd-note" id="pdNote" style="display:none">
        <i class="fas fa-info-circle" style="margin-top:1px"></i>
        <span id="pdNoteText"></span>
      </div>
      <div class="pd-loading" id="pdLoading">Memuat…</div>
      <table class="pd-tbl" id="pdTable" style="display:none">
        <thead>
          <tr><th>Tanggal</th><th class="num">Pemakaian</th><th class="num">Jam Aktif</th></tr>
        </thead>
        <tbody id="pdBody"></tbody>
      </table>
    </div>
    <div class="pd-foot">
      <button type="button" class="pd-btn" onclick="pdClose()">Tutup</button>
    </div>
  </div>
</div>
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

// ── Tab: Pemakaian per Area / Tren Pemakaian Harian ──
function pkSwitchTab(tab){
  document.querySelectorAll('.pk-tab-btn').forEach(function(btn){
    btn.classList.toggle('active', btn.dataset.tab === tab);
  });
  document.querySelectorAll('.pk-tab-panel').forEach(function(panel){
    panel.style.display = panel.dataset.panel === tab ? '' : 'none';
  });
}

// ── Modal Detail Pemakaian Harian ──
function pdRenderRows(hari){
  var html = '';
  var i = 0;
  // Kelompokkan rentang KOSONG DI AWAL (sebelum data pertama ada) jadi 1 baris,
  // biar nggak numpuk banyak baris "—" kalau fitur ini baru mulai jalan.
  var leadEnd = 0;
  while (leadEnd < hari.length && !hari[leadEnd].ada) leadEnd++;
  if (leadEnd > 1) {
    html += '<tr class="pd-empty-row"><td colspan="3">' +
      hari[0].tanggal_fmt + ' – ' + hari[leadEnd - 1].tanggal_fmt + ' · belum tercatat</td></tr>';
    i = leadEnd;
  }
  for (; i < hari.length; i++) {
    var h   = hari[i];
    var cls = h.hari_ini ? ' class="pd-today"' : '';
    var tgl = h.tanggal_fmt + (h.hari_ini ? ' · Hari ini' : '');
    html += '<tr' + cls + '><td>' + tgl + '</td><td class="num">' + h.bytes_fmt + '</td><td class="dur">' + h.jam_fmt + '</td></tr>';
  }
  return html || '<tr class="pd-empty-row"><td colspan="3">Belum ada data.</td></tr>';
}

function pdOpen(pelangganId){
  var ov      = document.getElementById('pdOverlay');
  var sub     = document.getElementById('pdSub');
  var note    = document.getElementById('pdNote');
  var noteTxt = document.getElementById('pdNoteText');
  var loading = document.getElementById('pdLoading');
  var table   = document.getElementById('pdTable');
  var body    = document.getElementById('pdBody');
  var bulan   = ov.dataset.bulan || '';

  sub.textContent  = 'Memuat…';
  note.style.display    = 'none';
  table.style.display   = 'none';
  loading.style.display = 'block';
  loading.textContent   = 'Memuat…';
  body.innerHTML = '';
  ov.classList.add('show');
  document.body.style.overflow = 'hidden';

  fetch('detail_harian.php?pelanggan_id=' + encodeURIComponent(pelangganId) + '&bulan=' + encodeURIComponent(bulan),
        {headers: {'X-Requested-With': 'fetch'}})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d || !d.ok) {
        loading.textContent = 'Gagal memuat data pemakaian harian.';
        return;
      }
      sub.textContent = d.pelanggan + ' · ' + d.area + ' · ' + d.periode_label;

      var hari0 = (d.hari && d.hari[0]) ? d.hari[0].tanggal : null;
      if (!d.mulai_tercatat) {
        noteTxt.textContent = 'Belum ada data harian tercatat untuk pelanggan ini di periode ini.';
        note.style.display = 'flex';
      } else if (d.mulai_tercatat !== hari0) {
        noteTxt.innerHTML = 'Data harian mulai tercatat sejak <b>' + d.mulai_tercatat_fmt +
          '</b>. Tanggal sebelumnya cuma ada total bulanan, belum ada rincian per hari.';
        note.style.display = 'flex';
      } else {
        note.style.display = 'none';
      }

      body.innerHTML = pdRenderRows(d.hari || []);
      loading.style.display = 'none';
      table.style.display   = 'table';
    })
    .catch(function(){
      loading.textContent = 'Gagal memuat data pemakaian harian.';
    });
}

function pdClose(){
  document.getElementById('pdOverlay').classList.remove('show');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') pdClose();
});
document.getElementById('pdOverlay') && document.getElementById('pdOverlay').addEventListener('click', function(e){
  if (e.target === this) pdClose();
});
</script>
HTML;

require __DIR__ . '/../../layout.php';
