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
    "SELECT COUNT(*) as n FROM pembayaran WHERE status='belum' AND bulan_tagihan < ?",
    [$bulan_ini]
)['n'] ?? 0;

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

// ── Tagihan belum lunas (bulan ini + tunggakan) ───────────────
$belum_payments = db_rows(
    "SELECT p.*, pl.nama as nama_pelanggan, pk.nama as nama_paket
     FROM pembayaran p
     LEFT JOIN pelanggan pl ON pl.id = p.pelanggan_id
     LEFT JOIN paket pk ON pk.id = p.paket_id
     WHERE p.status = 'belum'
     ORDER BY p.bulan_tagihan ASC, p.id ASC LIMIT 10"
);

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
    $terbaru_count = count($recent_payments);
    $belum_tab_count = count($belum_payments);
  ?>
  <div class="col-lg-8 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;padding-bottom:0;border-bottom:0">
        <span><i class="fas fa-receipt mr-2 text-primary"></i>Pembayaran Bulan Ini</span>
        <a href="<?= BASE_URL ?>modules/pembayaran/views.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
      </div>
      <!-- Tabs -->
      <div class="px-3 pt-2">
        <ul class="nav nav-tabs" id="tabPembayaran" role="tablist" style="border-bottom:1px solid #dee2e6">
          <li class="nav-item">
            <a class="nav-link active" id="tab-terbaru-link" data-toggle="tab" href="#tab-terbaru" role="tab">
              Terbaru
              <span class="badge badge-success ml-1"><?= $terbaru_count ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="tab-belum-link" data-toggle="tab" href="#tab-belum" role="tab">
              Belum Lunas
              <?php if ($belum_tab_count > 0): ?>
                <span class="badge badge-danger ml-1"><?= $belum_tab_count ?></span>
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

        <!-- Tab Belum Lunas -->
        <div class="tab-pane fade" id="tab-belum" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px">
              <thead>
                <tr>
                  <th>Pelanggan</th>
                  <th>Tagihan</th>
                  <th>Bulan</th>
                  <th>Status</th>
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
                    <td>
                      <?= rupiah((int)$p['jumlah']) ?>
                      <?php if ((int)$p['potongan'] > 0): ?>
                        <br><small class="text-warning"><?= $p['potongan'] ?> hari terjadwal</small>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M Y', strtotime($p['bulan_tagihan'] . '-01')) ?></td>
                    <td><?= badge_status('belum', $p['bulan_tagihan']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Semua tagihan sudah lunas.</td></tr>
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
