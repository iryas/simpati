<?php
// ============================================================
//  KAHFINET - Dashboard
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();

// ── Statistik ─────────────────────────────────────────────────
$total_pelanggan = db_row("SELECT COUNT(*) as n FROM pelanggan")['n'] ?? 0;
$aktif           = db_row("SELECT COUNT(*) as n FROM pelanggan WHERE status='aktif'")['n'] ?? 0;
$isolir          = db_row("SELECT COUNT(*) as n FROM pelanggan WHERE status='isolir'")['n'] ?? 0;
$total_paket     = db_row("SELECT COUNT(*) as n FROM paket")['n'] ?? 0;

$bulan_ini       = date('Y-m');
$pendapatan      = db_row(
    "SELECT COALESCE(SUM(terbayar),0) as total FROM pembayaran
     WHERE status='lunas' AND DATE_FORMAT(tgl_bayar,'%Y-%m') = ?",
    [$bulan_ini]
)['total'] ?? 0;

$belum_bayar     = db_row(
    "SELECT COUNT(*) as n FROM pembayaran WHERE status='belum' AND bulan_tagihan = ?",
    [$bulan_ini]
)['n'] ?? 0;

$tunggakan_count = db_row(
    "SELECT COUNT(DISTINCT pelanggan_id) as n FROM pembayaran WHERE status='belum' AND bulan_tagihan < ?",
    [$bulan_ini]
)['n'] ?? 0;

// ── Label periode bulan ini ───────────────────────────────────
$label_periode_dashboard = label_periode_tagihan($bulan_ini, (int)app_setting('tgl_mulai_tagihan', '1'));

// ── Pembayaran terbaru (lunas bulan ini, termasuk pelunasan tunggakan) ──
$recent_payments = db_rows(
    "SELECT p.*, pl.nama as nama_pelanggan, pk.nama as nama_paket
     FROM pembayaran p
     LEFT JOIN pelanggan pl ON pl.id = p.pelanggan_id
     LEFT JOIN paket pk ON pk.id = p.paket_id
     WHERE p.status = 'lunas' AND DATE_FORMAT(p.tgl_bayar, '%Y-%m') = ?
     ORDER BY p.tgl_bayar DESC, p.id DESC LIMIT 8",
    [$bulan_ini]
);

// ── Tagihan belum lunas bulan ini ────────────────────────────
$belum_payments = db_rows(
    "SELECT p.*, pl.nama as nama_pelanggan, pk.nama as nama_paket
     FROM pembayaran p
     LEFT JOIN pelanggan pl ON pl.id = p.pelanggan_id
     LEFT JOIN paket pk ON pk.id = p.paket_id
     WHERE p.status = 'belum' AND p.bulan_tagihan = ?
     ORDER BY p.id ASC LIMIT 10",
    [$bulan_ini]
);

// ── Tunggakan per baris, dikelompokkan di PHP ─────────────────
$tunggakan_rows = db_rows(
    "SELECT p.pelanggan_id, p.bulan_tagihan, p.jumlah, p.potongan,
            pl.nama as nama_pelanggan, pk.nama as nama_paket
     FROM pembayaran p
     LEFT JOIN pelanggan pl ON pl.id = p.pelanggan_id
     LEFT JOIN paket pk ON pk.id = p.paket_id
     WHERE p.status = 'belum' AND p.bulan_tagihan < ?
     ORDER BY pl.nama ASC, p.bulan_tagihan ASC",
    [$bulan_ini]
);

// Kelompokkan per pelanggan
$tunggakan_grouped = [];
foreach ($tunggakan_rows as $r) {
    $pid = $r['pelanggan_id'];
    if (!isset($tunggakan_grouped[$pid])) {
        $tunggakan_grouped[$pid] = [
            'nama_pelanggan' => $r['nama_pelanggan'],
            'nama_paket'     => $r['nama_paket'],
            'total'          => 0,
            'bulan'          => [],
        ];
    }
    $jumlah   = (int)$r['jumlah'];
    $potongan = (int)$r['potongan'];
    $terbayar = (int)round($jumlah - ($jumlah / 30 * $potongan));
    $tunggakan_grouped[$pid]['total'] += $terbayar;
    $tunggakan_grouped[$pid]['bulan'][] = [
        'bulan_tagihan' => $r['bulan_tagihan'],
        'jumlah'        => $jumlah,
        'terbayar'      => $terbayar,
        'potongan'      => $potongan,
    ];
}
$tunggakan_grouped = array_values($tunggakan_grouped);

// ── Pelanggan baru ────────────────────────────────────────────
$new_customers = db_rows(
    "SELECT pl.*, pk.nama as nama_paket
     FROM pelanggan pl
     LEFT JOIN paket pk ON pk.id = pl.paket_id
     ORDER BY pl.id DESC LIMIT 5"
);

// ── Render ────────────────────────────────────────────────────
$page_title  = 'Dashboard';
$active_menu = 'dashboard';

ob_start();
?>

<!-- Stat Cards -->
<div class="row mb-4">
  <div class="col-md-4 col-sm-6 mb-3">
    <div class="stat-card bg-primary-grad">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div>
        <div class="stat-label">Total Pelanggan</div>
        <div class="stat-value"><?= $total_pelanggan ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 mb-3">
    <div class="stat-card bg-success-grad">
      <div class="stat-icon"><i class="fas fa-user-check"></i></div>
      <div>
        <div class="stat-label">Pelanggan Aktif</div>
        <div class="stat-value"><?= $aktif ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 mb-3">
    <div class="stat-card bg-warning-grad">
      <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
      <div>
        <div class="stat-label">Diisolir</div>
        <div class="stat-value"><?= $isolir ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 mb-3">
    <div class="stat-card bg-cyan-grad">
      <div class="stat-icon"><i class="fas fa-box-open"></i></div>
      <div>
        <div class="stat-label">Total Paket</div>
        <div class="stat-value"><?= $total_paket ?></div>
      </div>
    </div>
  </div>
  <?php if (in_array(current_user()['role'], [ROLE_ADMIN, ROLE_KASIR])): ?>
  <div class="col-md-4 col-sm-6 mb-3">
    <div class="stat-card bg-purple-grad">
      <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
      <div>
        <div class="stat-label">Pendapatan Bulan Ini</div>
        <div class="stat-value" style="font-size:18px"><?= rupiah((int)$pendapatan) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 mb-3">
    <div class="stat-card bg-danger-grad">
      <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
      <div>
        <div class="stat-label">Belum Bayar (Bulan Ini)</div>
        <div class="stat-value"><?= $belum_bayar ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Row: Recent Payments + New Customers -->
<div class="row">
  <!-- Pembayaran Bulan Ini (tab) -->
  <?php if (in_array(current_user()['role'], [ROLE_ADMIN, ROLE_KASIR])): ?>
  <?php
    $terbaru_count    = count($recent_payments);
    $belum_count      = count($belum_payments);
    $tunggakan_count2 = count($tunggakan_grouped);
  ?>
  <div class="col-lg-8 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;padding-bottom:0;border-bottom:0">
        <div>
          <span><i class="fas fa-receipt mr-2 text-primary"></i>Pembayaran Bulan Ini</span>
          <br><small class="text-muted" style="font-size:11px;font-weight:normal">Periode <?= $label_periode_dashboard ?></small>
        </div>
        <a href="<?= BASE_URL ?>modules/pembayaran/views.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
      </div>
      <!-- Tabs -->
      <div class="px-3 pt-2">
        <ul class="nav nav-tabs" id="tabPembayaran" role="tablist" style="border-bottom:1px solid #dee2e6">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-terbaru" role="tab">
              <i class="fas fa-clock mr-1" style="font-size:12px"></i>Terbaru
              <span class="badge badge-success ml-1"><?= $terbaru_count ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-belum" role="tab">
              <i class="fas fa-hourglass-half mr-1" style="font-size:12px"></i>Belum Lunas
              <?php if ($belum_count > 0): ?>
                <span class="badge badge-danger ml-1"><?= $belum_count ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-tunggakan" role="tab">
              <i class="fas fa-fire mr-1" style="font-size:12px;color:#f59e0b"></i>Tunggakan
              <?php if ($tunggakan_count2 > 0): ?>
                <span class="badge badge-warning ml-1"><?= $tunggakan_count2 ?></span>
              <?php endif; ?>
            </a>
          </li>
        </ul>
      </div>
      <div class="tab-content">
        <!-- Tab Terbaru -->
        <div class="tab-pane fade show active" id="tab-terbaru" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px">
              <thead>
                <tr>
                  <th>Pelanggan</th>
                  <th>Jumlah</th>
                  <th>Tgl Bayar</th>
                  <th>Ket</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($recent_payments): ?>
                  <?php foreach ($recent_payments as $p): ?>
                  <?php $isTunggakan = $p['bulan_tagihan'] < $bulan_ini; ?>
                  <tr>
                    <td>
                      <div class="font-weight-bold"><?= clean($p['nama_pelanggan']) ?></div>
                      <small class="text-muted"><?= clean($p['nama_paket'] ?? '—') ?></small>
                    </td>
                    <td>
                      <?= rupiah((int)$p['terbayar'] ?: (int)$p['jumlah']) ?>
                      <?php if ((int)$p['potongan'] > 0): ?>
                        <br><small class="text-danger">- <?= rupiah((int)$p['jumlah'] - (int)$p['terbayar']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td><?= tgl_indo($p['tgl_bayar']) ?></td>
                    <td>
                      <?php if ($isTunggakan): ?>
                        <span class="badge badge-warning">Tunggakan</span>
                        <br><small class="text-muted"><?= date('M Y', strtotime($p['bulan_tagihan'] . '-01')) ?></small>
                      <?php else: ?>
                        <span class="badge badge-success">Lunas</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pembayaran bulan ini.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Tab Belum Lunas (bulan ini saja) -->
        <div class="tab-pane fade" id="tab-belum" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px">
              <thead>
                <tr>
                  <th>Pelanggan</th>
                  <th>Tagihan</th>
                  <th>Potongan</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($belum_payments): ?>
                  <?php foreach ($belum_payments as $p): ?>
                  <tr>
                    <td>
                      <div class="font-weight-bold"><?= clean($p['nama_pelanggan']) ?></div>
                      <small class="text-muted"><?= clean($p['nama_paket'] ?? '—') ?></small>
                    </td>
                    <td><?= rupiah((int)$p['jumlah']) ?></td>
                    <td>
                      <?php if ((int)$p['potongan'] > 0): ?>
                        <span class="text-warning"><?= $p['potongan'] ?> hari terjadwal</span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="3" class="text-center text-muted py-4">Semua tagihan bulan ini sudah lunas.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Tab Tunggakan (rowspan per pelanggan) -->
        <div class="tab-pane fade" id="tab-tunggakan" role="tabpanel">
          <?php
            $bulan_indo = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
          ?>
          <div class="table-responsive">
            <table class="table mb-0" style="font-size:13px;table-layout:fixed">
              <colgroup>
                <col style="width:36px">
                <col style="width:30%">
                <col style="width:22%">
                <col>
              </colgroup>
              <thead>
                <tr>
                  <th style="text-align:center">#</th>
                  <th>Pelanggan</th>
                  <th>Total tunggakan</th>
                  <th>Bulan belum dibayar</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($tunggakan_grouped): ?>
                  <?php foreach ($tunggakan_grouped as $i => $g): ?>
                  <?php $rowspan = count($g['bulan']); ?>
                    <?php foreach ($g['bulan'] as $bi => $b): ?>
                    <?php
                      $ts  = strtotime($b['bulan_tagihan'] . '-01');
                      $lbl = $bulan_indo[(int)date('n', $ts)] . ' ' . date('Y', $ts);
                      $isFirst = $bi === 0;
                      $isLast  = $bi === $rowspan - 1;
                      $subBg   = !$isFirst ? 'background:var(--light,#f8f9fa)' : '';
                    ?>
                    <tr style="<?= !$isLast ? 'border-bottom:0.5px solid var(--border)' : 'border-bottom:2px solid var(--border-strong)' ?>">
                      <?php if ($isFirst): ?>
                        <td rowspan="<?= $rowspan ?>" style="text-align:center;color:#6c757d;vertical-align:middle;font-size:12px;border-right:0.5px solid var(--border)"><?= $i + 1 ?></td>
                        <td rowspan="<?= $rowspan ?>" style="vertical-align:middle;border-right:0.5px solid var(--border)">
                          <div class="font-weight-bold"><?= clean($g['nama_pelanggan']) ?></div>
                          <small class="text-muted"><?= clean($g['nama_paket'] ?? '—') ?></small>
                        </td>
                        <td style="vertical-align:middle">
                          <div class="font-weight-bold text-danger"><?= rupiah($g['total']) ?></div>
                          <small class="text-muted"><?= $rowspan ?> bulan</small>
                        </td>
                      <?php else: ?>
                        <td style="<?= $subBg ?>;vertical-align:middle"></td>
                      <?php endif; ?>
                      <td style="<?= !$isFirst ? $subBg : '' ?>;vertical-align:middle">
                        <span class="badge badge-warning"><?= $lbl ?></span>
                        <?php if ($b['potongan'] > 0): ?>
                          <span class="ml-1" style="font-size:11px;text-decoration:line-through;color:#aaa"><?= rupiah($b['jumlah']) ?></span>
                          <span class="text-danger ml-1" style="font-size:11px"><?= rupiah($b['terbayar']) ?></span>
                          <br><small class="text-warning">potongan <?= $b['potongan'] ?> hari</small>
                        <?php else: ?>
                          <span class="text-muted ml-1" style="font-size:11px"><?= rupiah($b['jumlah']) ?></span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada tunggakan.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Pelanggan Baru -->
  <div class="col-lg-<?= in_array(current_user()['role'], [ROLE_ADMIN, ROLE_KASIR]) ? '4' : '6' ?> mb-4">
    <div class="card h-100">
      <div class="card-header">
        <span><i class="fas fa-user-plus mr-2 text-success"></i>Pelanggan Terbaru</span>
        <a href="<?= BASE_URL ?>modules/pelanggan/views.php" class="btn btn-sm btn-outline-success">
          Lihat Semua
        </a>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if ($new_customers): ?>
            <?php foreach ($new_customers as $c): ?>
            <li class="list-group-item px-4 py-3">
              <div class="d-flex align-items-center gap-2">
                <div class="user-avatar mr-3">
                  <i class="fas fa-user-circle fa-2x text-secondary"></i>
                </div>
                <div style="flex:1;min-width:0">
                  <div class="font-weight-600" style="font-size:13.5px;font-weight:600">
                    <?= clean($c['nama']) ?>
                  </div>
                  <small class="text-muted"><?= clean($c['nama_paket'] ?? '—') ?></small>
                </div>
                <?= badge_status($c['status']) ?>
              </div>
            </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="list-group-item text-center text-muted py-4">
              Belum ada pelanggan.
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../template.php';
