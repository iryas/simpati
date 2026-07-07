<?php
// ============================================================
//  KAHFINET - Modul Laporan > Keuntungan
//  Gabungan omset (pembayaran) vs pengeluaran, supaya kelihatan
//  laba/rugi bersih per bulan, bukan cuma omset.
// ============================================================
require_once __DIR__ . '/../../system/init.php';
auth_check();
auth_role([ROLE_ADMIN, ROLE_KEUANGAN]);

$tahun = (int)get('tahun', date('Y'));
$bulan = get('bulan', date('Y-m'));

// Omset per bulan (12 bulan tahun ini)
$per_bulan_omset = db_rows(
  "SELECT DATE_FORMAT(tgl_bayar,'%m') as bln, SUM(terbayar) as total
     FROM pembayaran
     WHERE status='lunas' AND YEAR(tgl_bayar) = ?
     GROUP BY DATE_FORMAT(tgl_bayar,'%m')
     ORDER BY bln ASC",
  [$tahun]
);
$bulan_omset_map = array_column($per_bulan_omset, null, 'bln');

// Pengeluaran per bulan (12 bulan tahun ini)
$per_bulan_pengeluaran = db_rows(
  "SELECT DATE_FORMAT(tanggal,'%m') as bln, SUM(jumlah) as total
     FROM pengeluaran
     WHERE YEAR(tanggal) = ?
     GROUP BY DATE_FORMAT(tanggal,'%m')
     ORDER BY bln ASC",
  [$tahun]
);
$bulan_pengeluaran_map = array_column($per_bulan_pengeluaran, null, 'bln');

// Rekap bulan dipilih
$totalOmsetBulan       = (int)db_row("SELECT COALESCE(SUM(terbayar),0) as n FROM pembayaran WHERE status = 'lunas' AND bulan_tagihan = ?", [$bulan])['n'];
$totalPengeluaranBulan = (int)db_row("SELECT COALESCE(SUM(jumlah),0) as n FROM pengeluaran WHERE bulan = ?", [$bulan])['n'];
$keuntunganBulan       = $totalOmsetBulan - $totalPengeluaranBulan;

// Breakdown pengeluaran per kategori, bulan dipilih
$breakdownPengeluaran = db_rows(
  "SELECT kategori, SUM(jumlah) as total
     FROM pengeluaran
     WHERE bulan = ?
     GROUP BY kategori
     ORDER BY FIELD(kategori, 'bandwidth', 'listrik', 'lainnya') ASC",
  [$bulan]
);
$labelKategori = ['bandwidth' => 'Bandwidth', 'listrik' => 'Listrik', 'lainnya' => 'Lainnya'];

$nama_bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

$page_title  = 'Laporan Keuntungan';
$active_menu = 'laporan_keuntungan';

ob_start();
?>

<div class="page-header">
  <h5><i class="fas fa-coins mr-2 text-primary"></i>Laporan Keuntungan</h5>
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
      <button type="submit" class="btn btn-primary btn-sm ml-2">
        <i class="fas fa-filter mr-1"></i>Terapkan
      </button>
    </form>
  </div>
</div>

<!-- Rekap Cards -->
<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid #2563eb">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Omset (<?= date('M Y', strtotime($bulan . '-01')) ?>)</div>
        <div class="font-weight-bold" style="font-size:18px;color:#2563eb"><?= rupiah($totalOmsetBulan) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid #ef4444">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Pengeluaran (<?= date('M Y', strtotime($bulan . '-01')) ?>)</div>
        <div class="font-weight-bold text-danger" style="font-size:18px"><?= rupiah($totalPengeluaranBulan) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card h-100" style="border-left:4px solid <?= $keuntunganBulan >= 0 ? '#10b981' : '#ef4444' ?>">
      <div class="card-body py-3">
        <div class="text-muted" style="font-size:12px">Keuntungan Bersih</div>
        <div class="font-weight-bold <?= $keuntunganBulan >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:18px"><?= rupiah($keuntunganBulan) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <!-- Grafik Bar Omset vs Pengeluaran -->
  <div class="col-lg-8 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <span><i class="fas fa-chart-bar mr-2 text-primary"></i>Omset vs Pengeluaran Tahun <?= $tahun ?></span>
      </div>
      <div class="card-body">
        <canvas id="chartKeuntungan" height="220"></canvas>
      </div>
    </div>
  </div>

  <!-- Breakdown Pengeluaran -->
  <div class="col-lg-4 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <span><i class="fas fa-receipt mr-2 text-danger"></i>Breakdown Pengeluaran</span>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if ($breakdownPengeluaran): foreach ($breakdownPengeluaran as $bp): ?>
              <li class="list-group-item px-4 py-3 d-flex justify-content-between align-items-center">
                <span style="font-size:13.5px;font-weight:600"><?= $labelKategori[$bp['kategori']] ?></span>
                <span class="text-danger font-weight-bold" style="font-size:13px"><?= rupiah((int)$bp['total']) ?></span>
              </li>
            <?php endforeach;
          else: ?>
            <li class="list-group-item text-center text-muted py-4">Belum ada pengeluaran bulan ini.</li>
          <?php endif; ?>
          <?php if ($breakdownPengeluaran): ?>
            <li class="list-group-item px-4 py-3 d-flex justify-content-between align-items-center bg-light">
              <span style="font-size:13.5px;font-weight:700">Total</span>
              <span class="font-weight-bold" style="font-size:13px"><?= rupiah($totalPengeluaranBulan) ?></span>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
  const labels = <?= json_encode(array_values($nama_bulan)) ?>.slice(1);
  const dataOmset = <?php
                  $d = [];
                  for ($m = 1; $m <= 12; $m++) {
                    $key = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $d[] = (int)($bulan_omset_map[$key]['total'] ?? 0);
                  }
                  echo json_encode($d);
                  ?>;
  const dataPengeluaran = <?php
                  $d = [];
                  for ($m = 1; $m <= 12; $m++) {
                    $key = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $d[] = (int)($bulan_pengeluaran_map[$key]['total'] ?? 0);
                  }
                  echo json_encode($d);
                  ?>;

  new Chart(document.getElementById('chartKeuntungan'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Omset (Rp)',
          data: dataOmset,
          backgroundColor: 'rgba(37,99,235,0.75)',
          borderRadius: 6,
        },
        {
          label: 'Pengeluaran (Rp)',
          data: dataPengeluaran,
          backgroundColor: 'rgba(239,68,68,0.75)',
          borderRadius: 6,
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: { boxWidth: 12, font: { size: 12 } }
        }
      },
      scales: {
        y: {
          ticks: {
            callback: v => 'Rp ' + v.toLocaleString('id-ID'),
            font: { size: 11 }
          }
        }
      }
    }
  });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../template.php';
