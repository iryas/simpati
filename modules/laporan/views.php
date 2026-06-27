<?php
// ============================================================
//  KAHFINET - Modul Laporan > Pendapatan
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KASIR]);

$tahun = (int)get('tahun', date('Y'));
$bulan = get('bulan', date('Y-m'));
$tipe  = get('tipe');

// Pendapatan per bulan (12 bulan tahun ini)
$per_bulan = db_rows(
  "SELECT DATE_FORMAT(tgl_bayar,'%m') as bln,
            SUM(jumlah) as total,
            COUNT(*) as n
     FROM pembayaran
     WHERE status='lunas' AND YEAR(tgl_bayar) = ?
     GROUP BY DATE_FORMAT(tgl_bayar,'%m')
     ORDER BY bln ASC",
  [$tahun]
);
$bulan_map = array_column($per_bulan, null, 'bln');

// Rekap bulan dipilih
$rekap = db_row(
  "SELECT COALESCE(SUM(CASE WHEN status='lunas' THEN terbayar ELSE 0 END),0) as total_lunas,
            COUNT(CASE WHEN status='lunas' THEN 1 END) as n_lunas,
            COUNT(CASE WHEN status='belum' THEN 1 END) as n_belum,
            COALESCE(SUM(CASE WHEN status='lunas' AND potongan = 0 THEN terbayar ELSE 0 END),0) as total_tanpa_potongan,
            COUNT(CASE WHEN status='lunas' AND potongan = 0 THEN 1 END) as n_tanpa_potongan,
            COALESCE(SUM(CASE WHEN status='lunas' AND potongan > 0 THEN terbayar ELSE 0 END),0) as total_dengan_potongan,
            COUNT(CASE WHEN status='lunas' AND potongan > 0 THEN 1 END) as n_dengan_potongan,
            COALESCE(SUM(CASE WHEN status='belum' THEN jumlah ELSE 0 END),0) as total_tunggakan,
            COUNT(CASE WHEN status='belum' THEN 1 END) as n_tunggakan
     FROM pembayaran WHERE bulan_tagihan = ?",
  [$bulan]
);

// Top 5 paket by pendapatan
$top_paket = db_rows(
  "SELECT pk.nama, COUNT(*) as n, SUM(py.jumlah) as total
     FROM pembayaran py
     JOIN paket pk ON pk.id = py.paket_id
     WHERE py.status='lunas' AND YEAR(py.tgl_bayar) = ?
     GROUP BY py.paket_id ORDER BY total DESC LIMIT 5",
  [$tahun]
);

// Detail bulan dipilih: tanpa potongan, dengan potongan, dan tunggakan
$detailWhere  = 'py.bulan_tagihan = ?';
$detailParams = [$bulan];

if ($tipe === 'tanpa_potongan') {
  $detailWhere .= " AND py.status='lunas' AND py.potongan = 0";
} elseif ($tipe === 'dengan_potongan') {
  $detailWhere .= " AND py.status='lunas' AND py.potongan > 0";
} elseif ($tipe === 'tunggakan') {
  $detailWhere .= " AND py.status='belum'";
}

$detail = db_rows(
  "SELECT py.*, pl.nama as nama_pelanggan, pk.nama as nama_paket
     FROM pembayaran py
     LEFT JOIN pelanggan pl ON pl.id = py.pelanggan_id
     LEFT JOIN paket pk ON pk.id = py.paket_id
     WHERE $detailWhere
     ORDER BY py.status ASC, py.potongan ASC, py.id DESC",
  $detailParams
);

$nama_bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

$page_title  = 'Laporan Pendapatan';
$active_menu = 'laporan_pendapatan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-chart-bar mr-2 text-primary"></i>Laporan Pendapatan</h5>
  <a href="<?= BASE_URL ?>modules/laporan/act.php?action=export_csv&bulan=<?= urlencode($bulan) ?>"
    class="btn btn-success btn-sm">
    <i class="fas fa-file-csv mr-1"></i>Export CSV
  </a>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="form-inline" style="gap:8px">
      <label class="mr-2 font-weight-bold" style="font-size:13px">Tahun:</label>
      <input type="number" name="tahun" class="form-control form-control-sm"
        value="<?= $tahun ?>" min="2020" max="<?= date('Y') ?>" style="width:90px">
      <label class="ml-3 mr-2 font-weight-bold" style="font-size:13px">Bulan Detail:</label>
      <input type="month" name="bulan" class="form-control form-control-sm" value="<?= $bulan ?>">
      <label class="ml-3 mr-2 font-weight-bold" style="font-size:13px">Tipe:</label>
      <select name="tipe" class="form-control form-control-sm">
        <option value="">Semua Tipe</option>
        <option value="tanpa_potongan" <?= $tipe === 'tanpa_potongan' ? 'selected' : '' ?>>Tanpa Potongan</option>
        <option value="dengan_potongan" <?= $tipe === 'dengan_potongan' ? 'selected' : '' ?>>Dengan Potongan</option>
        <option value="tunggakan" <?= $tipe === 'tunggakan' ? 'selected' : '' ?>>Tunggakan</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm ml-2">
        <i class="fas fa-filter mr-1"></i>Terapkan
      </button>
      <a href="<?= BASE_URL ?>modules/laporan/views.php?tahun=<?= $tahun ?>&bulan=<?= urlencode($bulan) ?>" class="btn btn-secondary btn-sm">Reset Tipe</a>
    </form>
  </div>
</div>

<!-- Rekap Cards -->
<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="stat-card bg-success-grad">
      <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
      <div>
        <div class="stat-label">Total Lunas (<?= date('M Y', strtotime($bulan . '-01')) ?>)</div>
        <div class="stat-value" style="font-size:18px"><?= rupiah((int)$rekap['total_lunas']) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="stat-card bg-primary-grad">
      <div class="stat-icon"><i class="fas fa-receipt"></i></div>
      <div>
        <div class="stat-label">Transaksi Lunas</div>
        <div class="stat-value"><?= $rekap['n_lunas'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="stat-card bg-danger-grad">
      <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
      <div>
        <div class="stat-label">Belum Bayar</div>
        <div class="stat-value"><?= $rekap['n_belum'] ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Rincian: Tanpa Potongan / Dengan Potongan / Tunggakan -->
<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid #10b981">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Lunas Tanpa Potongan</div>
        <div class="font-weight-bold text-success" style="font-size:18px">
          <?= rupiah((int)$rekap['total_tanpa_potongan']) ?>
        </div>
        <small class="text-muted"><?= $rekap['n_tanpa_potongan'] ?> transaksi</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid #f59e0b">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Lunas Dengan Potongan</div>
        <div class="font-weight-bold" style="font-size:18px;color:#f59e0b">
          <?= rupiah((int)$rekap['total_dengan_potongan']) ?>
        </div>
        <small class="text-muted"><?= $rekap['n_dengan_potongan'] ?> transaksi</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid #ef4444">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Tunggakan</div>
        <div class="font-weight-bold text-danger" style="font-size:18px">
          <?= rupiah((int)$rekap['total_tunggakan']) ?>
        </div>
        <small class="text-muted"><?= $rekap['n_tunggakan'] ?> tagihan</small>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <!-- Grafik Bar Pendapatan -->
  <div class="col-lg-8 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <span><i class="fas fa-chart-bar mr-2 text-primary"></i>Pendapatan Tahun <?= $tahun ?></span>
      </div>
      <div class="card-body">
        <canvas id="chartPendapatan" height="220"></canvas>
      </div>
    </div>
  </div>

  <!-- Top Paket -->
  <div class="col-lg-4 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <span><i class="fas fa-trophy mr-2 text-warning"></i>Top Paket Terlaris</span>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if ($top_paket): foreach ($top_paket as $i => $tp): ?>
              <li class="list-group-item px-4 py-3">
                <div class="d-flex align-items-center">
                  <span class="badge badge-primary mr-3"><?= $i + 1 ?></span>
                  <div style="flex:1">
                    <div style="font-size:13.5px;font-weight:600"><?= clean($tp['nama']) ?></div>
                    <small class="text-muted"><?= $tp['n'] ?> transaksi</small>
                  </div>
                  <span class="text-success font-weight-bold" style="font-size:13px">
                    <?= rupiah((int)$tp['total']) ?>
                  </span>
                </div>
              </li>
            <?php endforeach;
          else: ?>
            <li class="list-group-item text-center text-muted py-4">Belum ada data.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Detail Transaksi -->
<div class="card">
  <div class="card-header">
    <span><i class="fas fa-list mr-2"></i>Detail Tagihan — <?= date('F Y', strtotime($bulan . '-01')) ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Pelanggan</th>
            <th>Paket</th>
            <th>Jumlah</th>
            <th>Potongan</th>
            <th>Terbayar</th>
            <th>Tgl Bayar</th>
            <th>Tipe</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($detail): foreach ($detail as $i => $d): ?>
              <?php
              if ($d['status'] === 'belum') {
                $tipe = '<span class="badge badge-danger">Tunggakan</span>';
              } elseif ((int)$d['potongan'] > 0) {
                $tipe = '<span class="badge badge-warning">Dengan Potongan</span>';
              } else {
                $tipe = '<span class="badge badge-success">Tanpa Potongan</span>';
              }
              ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= clean($d['nama_pelanggan']) ?></td>
                <td><?= clean($d['nama_paket'] ?? '—') ?></td>
                <td><?= rupiah((int)$d['jumlah']) ?></td>
                <td>
                  <?php if ($d['status'] === 'lunas' && (int)$d['potongan'] > 0): ?>
                    <?= (int)$d['potongan'] ?> hari
                    <br><small class="text-danger">- <?= rupiah((int)$d['jumlah'] - (int)$d['terbayar']) ?></small>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= $d['status'] === 'lunas' ? rupiah((int)$d['terbayar']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $d['tgl_bayar'] ? tgl_indo($d['tgl_bayar']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $tipe ?></td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Tidak ada data tagihan pada bulan ini.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
  const labels = <?= json_encode(array_values($nama_bulan)) ?>.slice(1);
  const dataSet = <?php
                  $d = [];
                  for ($m = 1; $m <= 12; $m++) {
                    $key = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $d[] = (int)($bulan_map[$key]['total'] ?? 0);
                  }
                  echo json_encode($d);
                  ?>;

  new Chart(document.getElementById('chartPendapatan'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Pendapatan (Rp)',
        data: dataSet,
        backgroundColor: 'rgba(37,99,235,0.75)',
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          ticks: {
            callback: v => 'Rp ' + v.toLocaleString('id-ID'),
            font: {
              size: 11
            }
          }
        }
      }
    }
  });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../template.php';
